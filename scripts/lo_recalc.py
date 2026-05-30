import sys, os, time

# LibreOffice Python 脚本：打开xlsx → 重算 → 保存
# 用法：libreoffice --headless --norestore script.py file.xlsx
def recalc():
    doc = XSCRIPTCONTEXT.getDocument()
    doc.calculateAll()
    doc.store()
    doc.close(True)

recalc()
