import sys, os, json, re, time, urllib.request, ssl
from playwright.sync_api import sync_playwright

def main():
    if len(sys.argv) < 7:
        print("用法: python3 download_pzoom.py <username> <password> <login_url> <overview_url> <date> <prefix>")
        sys.exit(1)

    username, password, login_url, overview_url, date_str, prefix = sys.argv[1:7]

    script_dir = os.path.dirname(os.path.abspath(__file__))
    base_dir   = os.path.dirname(script_dir)
    data_dir   = os.path.join(base_dir, 'data')
    os.makedirs(data_dir, exist_ok=True)

    output_path = os.path.join(data_dir, f'{date_str}.xlsx')
    if os.path.exists(output_path):
        print(f"数据文件已存在: {output_path}")
        sys.exit(0)

    # Step 1: Login via Playwright, get token, close browser
    print("登录 pzoom...")
    token = None
    with sync_playwright() as p:
        b = p.chromium.launch(headless=True, args=["--no-sandbox"])
        pg = b.new_page(ignore_https_errors=True)
        pg.goto(login_url, timeout=20000, wait_until="domcontentloaded")
        pg.wait_for_timeout(1000)
        pg.locator('input[placeholder="用户名"]').fill(username)
        pg.locator('input[placeholder="密码"]').fill(password)
        pg.locator('button:has-text("登录")').click()
        pg.wait_for_timeout(5000)
        token = pg.evaluate("window.localStorage.getItem('token')")
        b.close()

    if not token:
        print("错误: 获取 token 失败"); sys.exit(1)
    print(f"  登录完成")

    # Step 2: Call API to find report_id
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    headers = {
        "Content-Type": "application/json;charset=UTF-8",
        "Token": token,
        "X-Port": "PINZHI",
        "x-menu": "838",
        "User-Agent": "Mozilla/5.0",
        "Origin": "https://app.pzoom.com",
    }

    # Derive report name from prefix (0940_时报 → 09:40_时报)
    time_match = re.match(r'(\d{2})(\d{2})_(.+)', prefix)
    if not time_match:
        print(f"错误: 无法解析前缀 {prefix}"); sys.exit(1)
    report_name_pattern = f"{time_match[1]}:{time_match[2]}_{time_match[3]}({date_str})"
    print(f"  匹配报告: {report_name_pattern}")

    # Call getReports API
    url = "https://app.pzoom.com/m-report-web/action/report/getReports"
    body = json.dumps({
        "execute_type": "ONCE",
        "iDisplayStart": 0,
        "iDisplayLength": 100,
        "network": "VIVO",
        "_v": "20260618.155518"
    }).encode()

    req = urllib.request.Request(url, data=body, headers=headers, method="POST")
    try:
        resp = urllib.request.urlopen(req, timeout=15, context=ctx)
        data = json.loads(resp.read())
    except Exception as e:
        print(f"错误: 获取报告列表失败: {str(e)[:100]}")
        sys.exit(1)

    result = data.get("result", [])
    if not result:
        print("错误: 报告列表为空"); sys.exit(1)

    # Find the matching report
    target = None
    for r in result:
        rname = r.get("report_name", "")
        if report_name_pattern in rname:
            target = r
            break

    if not target:
        print(f"错误: 未找到匹配的报告: {report_name_pattern}")
        print(f"  最近时报:")
        for r in result[:10]:
            if "时报" in r.get("report_name", ""):
                print(f"    [{r['report_id']}] {r['report_name']}")
        sys.exit(1)

    report_id = target["report_id"]
    print(f"  找到报告: [{report_id}] {target['report_name']}")

    # Step 3: Download the report
    print(f"  下载中...")
    url = "https://app.pzoom.com/m-report-web/action/report/download"
    body = json.dumps({"report_ids": [report_id], "_v": "20260618.155518"}).encode()
    req = urllib.request.Request(url, data=body, headers=headers, method="POST")
    
    try:
        resp = urllib.request.urlopen(req, timeout=30, context=ctx)
        content = resp.read()
        # Save to temp path first
        tmp_path = output_path + '.tmp'
        with open(tmp_path, "wb") as f:
            f.write(content)
        
        # Check if wrapped in a zip (pzoom API sometimes wraps xlsx in zip)
        import zipfile
        try:
            with zipfile.ZipFile(tmp_path, 'r') as zf:
                inner_xlsx = [n for n in zf.namelist() if n.endswith('.xlsx')]
                if len(inner_xlsx) == 1:
                    print(f"  检测到zip包裹，提取: {inner_xlsx[0]}")
                    inner_data = zf.read(inner_xlsx[0])
                    os.remove(tmp_path)
                    with open(output_path, 'wb') as f:
                        f.write(inner_data)
                else:
                    os.rename(tmp_path, output_path)
        except zipfile.BadZipFile:
            # Not a zip at all - normal xlsx
            os.rename(tmp_path, output_path)
        print(f"下载完成: {output_path}")
    except Exception as e:
        print(f"错误: 下载失败: {str(e)[:100]}")
        sys.exit(1)

if __name__ == '__main__':
    main()
