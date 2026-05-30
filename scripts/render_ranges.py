#!/usr/bin/env python3
"""
Recalculate + render chart shapes from xlsx to PNG via UNO PDF export.
Usage: render_ranges.py <xlsx_file> <output_dir>
"""
import subprocess, os, sys, time, shutil

if len(sys.argv) != 3:
    print("Usage: render_ranges.py <xlsx> <out_dir>", file=sys.stderr)
    sys.exit(1)

xlsx_path = os.path.abspath(sys.argv[1])
out_dir = os.path.abspath(sys.argv[2])
port = int(os.environ.get('LO_UNO_PORT', '2002'))

os.makedirs(out_dir, exist_ok=True)
safe_xlsx = '/var/www/html/tmp/_render_final.xlsx'
os.makedirs('/var/www/html/tmp', exist_ok=True)
shutil.copy2(xlsx_path, safe_xlsx)

subprocess.run(["pkill", "-9", "soffice.bin"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(1)

lo = subprocess.Popen(
    ["soffice", "--headless", "--norestore",
     f"--accept=socket,host=localhost,port={port};urp;"],
    env={"HOME": "/tmp"},
    stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
)

import uno
from com.sun.star.beans import PropertyValue

lc = uno.getComponentContext()
resolver = lc.ServiceManager.createInstanceWithContext(
    "com.sun.star.bridge.UnoUrlResolver", lc)

ctx = None
for attempt in range(15):
    time.sleep(1)
    try:
        ctx = resolver.resolve(
            f"uno:socket,host=localhost,port={port};urp;StarOffice.ComponentContext")
        break
    except Exception:
        continue

if not ctx:
    print("ERROR: Could not connect to LO UNO", file=sys.stderr)
    lo.kill(); lo.wait()
    sys.exit(1)

try:
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop", ctx)

    doc = desktop.loadComponentFromURL(
        f"file://{safe_xlsx}", "_blank", 0, ())
    doc.calculateAll()
    print("Recalculated")

    sheets = doc.getSheets()
    ctrl = doc.getCurrentController()

    # Collect all shape info first
    all_shapes = []  # [(sheet_name, sheet_index, shape_index, x_mm, y_mm, w_mm, h_mm)]
    chart_sheets = ['豆包爱学', '小说', '音乐']
    
    image_map = {
        '豆包爱学': [1],
        '小说': [3, 4],
        '音乐': [7, 8, 9],
    }

    for si in range(sheets.getCount()):
        sheet = sheets.getByIndex(si)
        name = sheet.getName()
        if name not in chart_sheets:
            continue
        
        dp = sheet.getDrawPage()
        indices = image_map.get(name, [])
        for di in range(min(dp.getCount(), len(indices))):
            shape = dp.getByIndex(di)
            pos = shape.Position if hasattr(shape, 'Position') else shape.getPosition()
            sz = shape.Size if hasattr(shape, 'Size') else shape.getSize()
            all_shapes.append((name, si, di, indices[di], pos.X, pos.Y, sz.Width, sz.Height))
            print(f"  {name}[{di}]: image{indices[di]} ({pos.X},{pos.Y}) {sz.Width}x{sz.Height}")

    # Export each sheet as PDF
    for si in range(sheets.getCount()):
        sheet = sheets.getByIndex(si)
        name = sheet.getName()
        if name not in chart_sheets:
            continue

        ctrl.setActiveSheet(sheet)
        pdf_path = f"/tmp/_sheet_{si}.pdf"
        
        pdf_filter = (
            PropertyValue("FilterName", 0, "calc_pdf_Export", 0),
            PropertyValue("URL", 0, f"file://{pdf_path}", 0),
        )
        doc.storeToURL(f"file://{pdf_path}", pdf_filter)
        print(f"  {name} PDF: {os.path.getsize(pdf_path)}B")

    doc.close(True)

    # Crop each shape from its sheet's PDF
    dpi = 200
    for name, sheet_idx, shape_idx, img_idx, x_100mm, y_100mm, w_100mm, h_100mm in all_shapes:
        pdf_path = f"/tmp/_sheet_{sheet_idx}.pdf"
        out_png = os.path.join(out_dir, f"image{img_idx}.png")
        
        # Add padding for labels/borders
        pad = 500  # 1/100mm padding
        x = max(0, x_100mm - pad)
        y = max(0, y_100mm - pad)
        w = w_100mm + pad * 2
        h = h_100mm + pad * 2
        
        # 1/100mm to points: * 72 / 2540
        x_pt = x * 72 / 2540
        y_pt = y * 72 / 2540
        w_pt = w * 72 / 2540
        h_pt = h * 72 / 2540
        
        # Points to pixels at DPI
        x_px = int(x_pt * dpi / 72)
        y_px = int(y_pt * dpi / 72)
        w_px = int(w_pt * dpi / 72)
        h_px = int(h_pt * dpi / 72)
        
        subprocess.run([
            "convert", "-density", str(dpi),
            f"{pdf_path}[0]",
            "-crop", f"{w_px}x{h_px}+{x_px}+{y_px}",
            "+repage",
            out_png
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30)
        
        if os.path.exists(out_png):
            print(f"  image{img_idx}.png: {os.path.getsize(out_png)}B ({w_px}x{h_px})")
        else:
            print(f"  image{img_idx}.png: FAILED", file=sys.stderr)

    # Cleanup
    for si in range(sheets.getCount()):
        pdf_path = f"/tmp/_sheet_{si}.pdf"
        try: os.remove(pdf_path)
        except: pass
    try: os.remove(safe_xlsx)
    except: pass

    print("Done")

except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    import traceback; traceback.print_exc(file=sys.stderr)
    sys.exit(1)
finally:
    lo.kill()
    lo.wait()
