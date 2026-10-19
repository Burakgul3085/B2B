"""
Demo vitrin videoları (yalnızca yerel geliştirme). Kullanım: php artisan demo:videolar

Ürün çizimlerinin saydam PNG'lerinden (sprites/) iki kısa, döngüye uygun sahne üretir ve
tarayıcıda oynayan WebM (VP9) olarak yazar (OpenCV'nin FFmpeg arka ucu; küçük dosya, yüksek kalite). Metin yoktur;
başlık ve düğme slayt üzerine HTML olarak yazılır. Her video için ilk kare kapak görseli olarak kaydedilir.

Gerekenler: Python 3, numpy, Pillow, opencv-python.
"""
import math
import sys
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

W, H, FPS = 1920, 600, 30


def gradient(dark, mid, w=W, h=H):
    t = np.linspace(0, 1, w)[None, :, None]
    a = np.array(dark, np.float32)[None, None, :]
    b = np.array(mid, np.float32)[None, None, :]
    return np.repeat(a + (b - a) * t, h, axis=0)


def glow(frame, cx, cy, radius, color, strength):
    h, w, _ = frame.shape
    y, x = np.ogrid[:h, :w]
    d = np.sqrt((x - cx) ** 2 + (y - cy) ** 2) / radius
    a = np.clip(1 - d, 0, 1) ** 2 * strength
    return frame * (1 - a[..., None]) + np.array(color, np.float32) * a[..., None]


def dots(frame, offset, color=(255, 255, 255), alpha=0.07, step=26, until=0.4):
    out = frame.copy()
    for yy in range(step // 2, H, step):
        for xx in range(int(step / 2 + offset % step), int(W * until), step):
            out[yy - 1:yy + 2, xx - 1:xx + 2] = out[yy - 1:yy + 2, xx - 1:xx + 2] * (1 - alpha) + np.array(color) * alpha
    return out


def shadow(frame, cx, cy, width, alpha=0.35):
    h, w, _ = frame.shape
    y, x = np.ogrid[:h, :w]
    d = ((x - cx) / (width / 2)) ** 2 + ((y - cy) / (width / 9)) ** 2
    a = np.clip(1 - d, 0, 1) ** 1.6 * alpha
    return frame * (1 - a[..., None])


def paste(frame, sprite, cx, cy, size, angle=0.0, opacity=1.0):
    img = sprite.resize((size, size), Image.LANCZOS)
    if angle:
        img = img.rotate(angle, resample=Image.BICUBIC, expand=False)
    arr = np.asarray(img, np.float32)
    rgb, a = arr[..., :3], arr[..., 3:4] / 255.0 * opacity
    x0, y0 = int(cx - size / 2), int(cy - size / 2)
    fx0, fy0 = max(0, x0), max(0, y0)
    fx1, fy1 = min(W, x0 + size), min(H, y0 + size)
    if fx0 >= fx1 or fy0 >= fy1:
        return frame
    sx0, sy0 = fx0 - x0, fy0 - y0
    region = frame[fy0:fy1, fx0:fx1]
    sr, sa = rgb[sy0:sy0 + fy1 - fy0, sx0:sx0 + fx1 - fx0], a[sy0:sy0 + fy1 - fy0, sx0:sx0 + fx1 - fx0]
    frame[fy0:fy1, fx0:fx1] = region * (1 - sa) + sr * sa
    return frame


def ease_out(t):
    return 1 - (1 - min(max(t, 0), 1)) ** 3


def scene_season(sprites, n):
    """Ürünler sırayla sağdan süzülerek gelir, hafifçe salınır; ışık yavaşça gezinir."""
    base = gradient((7, 18, 52), (30, 64, 175))
    items = [  # sprite, x, y, boyut, açı, gecikme
        ('notebook_orange', 0.60, 0.50, 520, -8, 0.0),
        ('notebook_blue', 0.73, 0.47, 560, 6, 0.25),
        ('pencilbox', 0.86, 0.53, 470, -4, 0.5),
        ('ruler', 0.68, 0.82, 420, -6, 0.75),
        ('gel_green', 0.93, 0.25, 300, 14, 1.0),
    ]
    for f in range(n):
        t = f / FPS
        loop = f / n * 2 * math.pi
        frame = glow(base.copy(), W * (0.70 + 0.05 * math.sin(loop)), H * 0.5, 700, (56, 189, 248), 0.55)
        frame = dots(frame, f * 0.6)
        for name, px, py, size, angle, delay in items:
            k = ease_out((t - delay) / 1.2)
            bob = math.sin(loop * 2 + delay * 3) * 10
            cx = W * px + (1 - k) * 500
            cy = H * py + bob
            frame = shadow(frame, cx, H * py + size * 0.36, size * 0.62, 0.32 * k)
            frame = paste(frame, sprites[name], cx, cy, size, angle + math.sin(loop + delay) * 2.5, k)
        yield frame


def scene_flow(sprites, n):
    """Ürünler sağdan sola kesintisiz akar (döngüye uygun), arkada sıcak ışık nabız gibi atar."""
    base = gradient((67, 20, 7), (194, 65, 12))
    names = ['paint', 'pencilbox_color', 'markers', 'highlighter', 'sticky', 'stapler', 'paper', 'glue']
    spacing = 330
    span = spacing * len(names)
    for f in range(n):
        loop = f / n * 2 * math.pi
        frame = glow(base.copy(), W * 0.74, H * 0.5, 650 + 40 * math.sin(loop * 2), (252, 211, 77), 0.5)
        frame = dots(frame, -f * 0.8)
        shift = f / n * span
        for i, name in enumerate(names):
            x = (W * 0.42 + i * spacing - shift) % span + W * 0.42 - spacing
            if x < W * 0.40 - 200 or x > W + 200:
                continue
            fade = min(1.0, max(0.0, (x - (W * 0.40 - 200)) / 260))
            y = H * 0.52 + math.sin(loop * 2 + i) * 14
            size = 300
            frame = shadow(frame, x, y + size * 0.38, size * 0.6, 0.3 * fade)
            frame = paste(frame, sprites[name], x, y, size, math.sin(loop + i) * 6, fade)
        yield frame


def write(path, frames):
    writer = cv2.VideoWriter(str(path), cv2.CAP_FFMPEG, cv2.VideoWriter_fourcc(*'VP90'), FPS, (W, H))
    if not writer.isOpened():
        raise SystemExit('VP9 kodlayıcı açılamadı (opencv-python FFmpeg desteğiyle kurulmalı).')
    first = None
    for frame in frames:
        bgr = cv2.cvtColor(np.clip(frame, 0, 255).astype(np.uint8), cv2.COLOR_RGB2BGR)
        if first is None:
            first = bgr
        writer.write(bgr)
    writer.release()
    cv2.imwrite(str(path.with_suffix('.png')), first)


def main(directory):
    root = Path(directory)
    sprites = {p.stem: Image.open(p).convert('RGBA') for p in (root / 'sprites').glob('*.png')}
    seconds = 10
    write(root / 'tanitim-yeni-sezon.webm', scene_season(sprites, FPS * seconds))
    write(root / 'tanitim-urun-akisi.webm', scene_flow(sprites, FPS * seconds))
    print('ok')


if __name__ == '__main__':
    main(sys.argv[1])
