#!/usr/bin/env python3
"""
Force recalculation + render sheets to PNG via UNO.
Usage: recalc_xlsx.py <input.xlsx> <output.xlsx> [<render_dir>]
"""
import subprocess, os, sys, time, shutil

render_dir = None
if len(sys.argv) == 4:
    render_dir = os.path.abspath(sys.argv[3])
elif len(sys.argv) != 3:
    print("Usage: recalc_xlsx.py <input.xlsx> <output.xlsx> [<render_dir>]", file=sys.stderr)
    sys.exit(1)

input_path = os.path.abspath(sys.argv[1])
output_path = os.path.abspath(sys.argv[2])
port = int(os.environ.get('LO_UNO_PORT', '2002'))
tmpdir = '/var/www/html/tmp/_recalc'

os.makedirs(tmpdir, exist_ok=True)
safe_input = os.path.join(tmpdir, '_in.xlsx')
safe_output = os.path.join(tmpdir, '_out.xlsx')
shutil.copy2(input_path, safe_input)

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

localContext = uno.getComponentContext()
resolver = localContext.ServiceManager.createInstanceWithContext(
    "com.sun.star.bridge.UnoUrlResolver", localContext)

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
    print("ERROR: Could not connect to LibreOffice UNO", file=sys.stderr)
    lo.kill(); lo.wait()
    sys.exit(1)

try:
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop", ctx)
    smgr = ctx.ServiceManager

    doc = desktop.loadComponentFromURL(
        f"file://{safe_input}", "_blank", 0, ())
    doc.calculateAll()

    # Get frame for dispatch
    frame = doc.getCurrentController().getFrame()

    if render_dir:
        os.makedirs(render_dir, exist_ok=True)
        chart_sheets = ['豆包爱学', '小说', '音乐']

        sheets = doc.getSheets()
        controller = doc.getCurrentController()

        for si in range(sheets.getCount()):
            sheet = sheets.getByIndex(si)
            name = sheet.getName()
            if name not in chart_sheets:
                continue

            controller.setActiveSheet(sheet)

            # Get the used range and select it for export
            cursor = sheet.createCursor()
            cursor.gotoEndOfUsedArea(True)
            
            # Export via uno:ExportTo dispatch
            # First: store the full page PNG
            out_png = os.path.join(render_dir, f"_sheet_{si}.png")
            out_url = f"file://{out_png}"

            # Use dispatch .uno:ExportTo
            dispatch_helper = smgr.createInstanceWithContext(
                "com.sun.star.frame.DispatchHelper", ctx)

            props = (
                PropertyValue("URL", 0, out_url, 0),
                PropertyValue("FilterName", 0, "png", 0),
            )
            dispatch_helper.executeDispatch(frame, ".uno:ExportTo", "", 0, props)
            time.sleep(1)

            if os.path.exists(out_png):
                print(f"  {name} -> {os.path.getsize(out_png)}B")
            else:
                print(f"  xx {name} PNG not created", file=sys.stderr)

    # --- Save the recalculated xlsx ---
    props = (PropertyValue("FilterName", 0, "Calc MS Excel 2007 XML", 0),)
    doc.storeToURL(f"file://{safe_output}", props)
    doc.close(True)

    shutil.move(safe_output, output_path)
    print("OK")

except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    import traceback; traceback.print_exc(file=sys.stderr)
    sys.exit(1)

finally:
    lo.kill()
    lo.wait()
    for f in [safe_input, safe_output]:
        try:
            os.remove(f)
        except (OSError, FileNotFoundError):
            pass
