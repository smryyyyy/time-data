import sys, os, time, zipfile, re
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

    download_dir = os.path.join(base_dir, 'tmp', 'download')
    os.makedirs(download_dir, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, args=['--ignore-certificate-errors','--no-sandbox'])
        context = browser.new_context(accept_downloads=True, ignore_https_errors=True)
        page = context.new_page()

        try:
            print("登录 pzoom...")
            page.goto(login_url, timeout=30000, wait_until='domcontentloaded')
            page.wait_for_timeout(3000)
            page.locator('input[placeholder="用户名"]').fill(username)
            page.locator('input[placeholder="密码"]').fill(password)
            page.locator('button:has-text("登录")').click()
            page.wait_for_timeout(8000)
            print(f"  登录完成: {page.title()}")

            ts = int(time.time() * 1000)
            page.goto(f'{overview_url}?t={ts}', timeout=30000, wait_until='domcontentloaded')
            page.wait_for_timeout(5000)
            print(f"  进入: {page.title()}")

            # 关闭可能弹出的认证对话框
            for i in range(5):
                try:
                    # ESC 关弹窗
                    page.keyboard.press('Escape')
                    page.wait_for_timeout(300)
                except: pass
                # 各种关闭按钮
                for sel in [
                    '.el-message-box__headerbtn',
                    '.el-dialog__headerbtn', 
                    '.el-drawer__close-btn',
                    '.el-message-box__close',
                    '.el-icon-close',
                    'button[aria-label="Close"]',
                    'button:has-text("取消")',
                    'button:has-text("确定")',
                ]:
                    try:
                        btn = page.locator(sel).first
                        if btn.is_visible():
                            btn.click()
                            page.wait_for_timeout(300)
                            print(f"  关闭弹窗: {sel}")
                    except: pass
                page.wait_for_timeout(300)

            # 在表格中找"时报"行
            found = False
            # 先列出前几行看格式
            rows = page.locator('.el-table__row, tr').all()
            matched_rows = []
            for row in rows:
                try:
                    text = row.inner_text().strip()
                    if '时报' in text:
                        matched_rows.append(text)
                except:
                    continue

            print(f"  共 {len(rows)} 行, 其中时报 {len(matched_rows)} 行")
            if len(matched_rows) > 0:
                print(f"  首条样例: {matched_rows[0][:200]}")

            # 找到目标行，按报告名匹配（格式：09:40_时报(日期)）
            target_row = None
            # 将 "0940_时报" 转为 "09:40_时报" 用于匹配
            time_match = re.match(r'(\d{2})(\d{2})_时报', prefix)
            report_pattern = f'{time_match[1]}:{time_match[2]}_时报' if time_match else prefix
            print(f'  匹配模式: {report_pattern} + {date_str}')
            
            for row in rows:
                try:
                    text = row.inner_text().strip()
                    if '时报' in text and date_str in text:
                        # 进一步读取报告名列验证
                        cols = row.locator('td').all()
                        for col in cols:
                            col_text = col.inner_text().strip()
                            if report_pattern in col_text and date_str in col_text:
                                target_row = row
                                break
                        if target_row:
                            break
                except:
                    continue

            if target_row:
                # JS 直接勾选该行的复选框
                try:
                    target_row.evaluate('el => { const cb = el.querySelector(".el-checkbox__original"); if(cb){cb.checked=true; cb.dispatchEvent(new Event("change",{bubbles:true})); cb.dispatchEvent(new Event("input",{bubbles:true})); } }')
                    page.wait_for_timeout(500)
                    print("  已勾选目标行 (JS)")
                except:
                    print("  JS勾选失败")
                    target_row.click()
                    page.wait_for_timeout(1000)

                # 点击"批量操作"下拉 → "下载报告"
                batch_btn = page.locator('button:has-text("批量操作")').first
                if batch_btn.is_visible():
                    batch_btn.click()
                    page.wait_for_timeout(1000)

                    # 尝试直接点击"下载报告"
                    dl_item = page.locator('li:has-text("下载报告"):not(.is-disabled)').first
                    if dl_item.is_visible():
                        with page.expect_download(timeout=30000) as dl_info:
                            dl_item.click()
                        dl = dl_info.value
                        fname = os.path.basename(dl.suggested_filename)
                        full = os.path.join(download_dir, fname)
                        dl.save_as(full)
                        print(f"  下载: {fname}")
                        found = True
                    else:
                        # 可能弹出新对话框，截图看看
                        page.wait_for_timeout(2000)
                        page.screenshot(path=os.path.join(data_dir, f'dl_dialog_{date_str}.png'))
                        print("  截图已保存，查看下载对话框")
                        # 尝试页面级下载按钮
                        for btn_text in ['下载', '确定', '确认', '提交']:
                            btn = page.locator(f'button:has-text("{btn_text}")').first
                            if btn.is_visible():
                                try:
                                    with page.expect_download(timeout=30000) as dl_info:
                                        btn.click()
                                    dl = dl_info.value
                                    fname = os.path.basename(dl.suggested_filename)
                                    full = os.path.join(download_dir, fname)
                                    dl.save_as(full)
                                    print(f"  下载: {fname}")
                                    found = True
                                    break
                                except:
                                    continue

            if not found:
                # 兜底：尝试页面级下载按钮
                for btn_text in ['下载', '导出', '下载报告']:
                    btn = page.locator(f'button:has-text("{btn_text}"), a:has-text("{btn_text}")').first
                    if btn.is_visible():
                        try:
                            with page.expect_download(timeout=60000) as dl_info:
                                btn.click()
                            dl = dl_info.value
                            fname = os.path.basename(dl.suggested_filename)
                            full = os.path.join(download_dir, fname)
                            dl.save_as(full)
                            print(f"  兜底下载: {fname}")
                            found = True
                            break
                        except:
                            continue

            if not found:
                page.screenshot(path=os.path.join(data_dir, f'debug_{date_str}.png'))
                # 保存 HTML 用于调试
                html_path = os.path.join(data_dir, f'page_{date_str}.html')
                with open(html_path, 'w', encoding='utf-8') as hf:
                    hf.write(page.content())
                print(f"HTML 已保存: {html_path}")
                print(f"错误：未找到 {date_str} 的时报报告")
                if matched_rows:
                    print(f"  时报行日期范围: ")
                    for t in matched_rows[:5]:
                        print(f"    {t[:150]}")
                sys.exit(1)

            if full.endswith('.zip'):
                with zipfile.ZipFile(full, 'r') as zf:
                    zf.extractall(download_dir)
                found_xlsx = None
                for root, dirs, files in os.walk(download_dir):
                    for f in files:
                        if f.endswith('.xlsx'):
                            found_xlsx = os.path.join(root, f); break
                    if found_xlsx: break
                if not found_xlsx:
                    print("错误：zip 解压后未找到 xlsx"); sys.exit(1)
                full = found_xlsx

            # 使用 shutil.move 支持跨设备移动（Docker volumes 不同挂载点）
            import shutil
            shutil.move(full, output_path)
            print(f"下载完成: {output_path}")

        except Exception as e:
            page.screenshot(path=os.path.join(data_dir, f'error_{date_str}.png'))
            print(f"错误: {e}")
            sys.exit(1)
        finally:
            browser.close()

if __name__ == '__main__':
    main()
