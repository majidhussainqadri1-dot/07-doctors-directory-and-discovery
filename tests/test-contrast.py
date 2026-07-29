#!/usr/bin/env python3
from __future__ import annotations


def rgb(hex_value: str) -> tuple[int, int, int]:
    value = hex_value.lstrip("#")
    return tuple(int(value[i:i+2], 16) for i in (0, 2, 4))


def channel(value: int) -> float:
    c = value / 255.0
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def luminance(hex_value: str) -> float:
    r, g, b = rgb(hex_value)
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)


def contrast(a: str, b: str) -> float:
    high, low = sorted((luminance(a), luminance(b)), reverse=True)
    return (high + 0.05) / (low + 0.05)


pairs = {
    "Sabri Orange / dark button text": ("#ff8a1f", "#24160a"),
    "WhatsApp green / white": ("#08713f", "#ffffff"),
    "Message blue / white": ("#1f5ea8", "#ffffff"),
}

for label, (background, foreground) in pairs.items():
    ratio = contrast(background, foreground)
    print(f"{label}: {ratio:.2f}:1")
    if ratio < 4.5:
        raise SystemExit(f"FAIL: {label} is below WCAG AA normal-text contrast")

print("All tested color pairs meet WCAG AA normal-text contrast.")
