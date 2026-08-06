#!/usr/bin/env python3
from __future__ import annotations

def rgb(value: str) -> tuple[int, int, int]:
    value = value.lstrip('#')
    return tuple(int(value[i:i+2], 16) for i in (0, 2, 4))

def channel(value: int) -> float:
    c = value / 255.0
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

def luminance(value: str) -> float:
    r, g, b = rgb(value)
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)

def contrast(a: str, b: str) -> float:
    high, low = sorted((luminance(a), luminance(b)), reverse=True)
    return (high + 0.05) / (low + 0.05)

pairs = {
    'Primary green / white': ('#0f6b3f', '#ffffff'),
    'Dark green / white': ('#084a2c', '#ffffff'),
    'Ink / white': ('#152033', '#ffffff'),
    'Warning text / light background': ('#7a4b00', '#fff3d7'),
}
for label, pair in pairs.items():
    ratio = contrast(*pair)
    print(f'{label}: {ratio:.2f}:1')
    if ratio < 4.5:
        raise SystemExit(f'FAIL: {label}')
print('PASS: all tested text pairs meet WCAG AA normal-text contrast')
