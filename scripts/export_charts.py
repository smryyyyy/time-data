#!/usr/bin/env python3
"""
从重算后的 xlsx 中导出图表为 PNG（替代 EMF 提取流程）。
Usage: export_charts.py <recalculated.xlsx> <output_dir>
"""
import subprocess, os, sys, time, shutil

if len(sys.argv) != 3:
    print("Usage: export_charts.py <recalculated.xlsx> <output_dir>", file=sys.stderr)
    sys.exit(1)

input_path = os.path.abspath(sys.argv[1])
output_dir = os.path.abspath(sys.argv[2])
os.makedirs(output_dir, exist_ok=True)
port = int(os.environ.get('LO_UNO_PORT', '2002'))

# Copy to safe path
safe_input = '/var/www/html/tmp/_chart_in.xlsx'
os.makedirs('/var/www/html/tmp', exist_ok=True)
shutil.copy2(input_path, safe_input)

# Kill leftover soffice
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
    print("ERROR: Could not connect to LO UNO", file=sys.stderr)
    lo.kill(); lo.wait()
    sys.exit(1)

try:
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop", ctx)
    smgr = ctx.ServiceManager

    doc = desktop.loadComponentFromURL(
        f"file://{safe_input}", "_blank", 0, ())

    # Force recalculation + chart refresh
    doc.calculateAll()

    # Export each sheet as high-res PNG
    sheets = doc.getSheets()
    exported = []

    for si in range(sheets.getCount()):
        sheet = sheets.getByIndex(si)
        sheet_name = sheet.getName()

        # Skip 今日/昨日 raw data sheets
        if sheet_name in ['今日', '昨日']:
            continue

        # Check if sheet has charts
        charts = sheet.getCharts()
        if charts.getCount() == 0:
            continue

        print(f"Sheet '{sheet_name}': {charts.getCount()} chart(s)")

        # Export entire sheet as PNG at high resolution
        # We use the sheet's draw page to find chart shapes
        draw_page = sheet.getDrawPage()
        for di in range(draw_page.getCount()):
            shape = draw_page.getByIndex(di)
            shape_type = shape.getShapeType()

            # Chart shapes have type com.sun.star.drawing.OLE2Shape
            if 'OLE2' not in shape_type:
                continue

            # Get the chart model
            try:
                chart_model = shape.getModel()
            except:
                continue

            # Get position and size
            pos = shape.getPosition()
            size = shape.getSize()

            # Export range: expanded slightly to include labels
            x = int(max(0, pos.X - 100))
            y = int(max(0, pos.Y - 100))
            w = int(size.Width + 200)
            h = int(size.Height + 200)

            # Build the range to export (convert 1/100mm to cell ref)
            # Simpler approach: export using DispatchHelper

            idx = len(exported) + 1
            out_file = os.path.join(output_dir, f"image{idx}.png")

            # Select the chart shape
            doc.getCurrentController().select(shape)

            # Use GraphicExportFilter
            try:
                export_filter = smgr.createInstanceWithContext(
                    "com.sun.star.drawing.GraphicExportFilter", ctx)
                export_filter.setSourceDocument(doc)

                props = (
                    PropertyValue("MediaType", 0, "image/png", 0),
                    PropertyValue("URL", 0, f"file://{out_file}", 0),
                    PropertyValue("FilterName", 0, "png", 0),
                )
                export_filter.filter(props)
                print(f"  → {os.path.basename(out_file)} ({w}x{h})")
                exported.append(out_file)
            except Exception as e:
                print(f"  ✗ Export failed: {e}", file=sys.stderr)

    doc.close(True)
    print(f"OK: {len(exported)} charts exported")

except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
finally:
    lo.kill()
    lo.wait()
    try:
        os.remove(safe_input)
    except OSError:
        pass
