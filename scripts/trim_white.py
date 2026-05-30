#!/usr/bin/env python3
"""裁剪 PNG 白边"""
import sys
from PIL import Image
import numpy as np

for path in sys.argv[1:]:
    img = Image.open(path).convert('RGB')
    arr = np.array(img)
    # 非白色像素（阈值230）
    row_mask = np.any(np.all(arr < 230, axis=2), axis=1)
    col_mask = np.any(np.all(arr < 230, axis=2), axis=0)
    if row_mask.any() and col_mask.any():
        y1, y2 = np.where(row_mask)[0][[0, -1]]
        x1, x2 = np.where(col_mask)[0][[0, -1]]
        cropped = img.crop((x1, y1, x2 + 1, y2 + 1))
        cropped.save(path)
        print(f'OK {img.size} → {cropped.size}')
    else:
        print(f'SKIP {path}')
