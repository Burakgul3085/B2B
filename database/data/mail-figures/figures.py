"""
Sipariş e-postalarının hareketli figürleri (Yapboz'a özgü blok karakter). Çıktı: resources/images/mail/*.gif

  python -I database/data/mail-figures/figures.py resources/images/mail

- siparis-alindi.gif : figür el sallar, yanında Yapboz kutusu ve onay rozeti, yapboz parçaları süzülür.
- kargoda.gif        : figür Yapboz kargo arabasını sürer; tekerlekler döner, yol ve bulutlar akar.

İlk kare tek başına da anlamlıdır (Outlook masaüstü yalnızca ilk kareyi gösterir). Çizim 2 kat büyük yapılıp küçültülür.
Pillow gerekir.
"""
import math
import os
import sys

from PIL import Image, ImageDraw, ImageFont

S = 2  # çizim ölçeği
W, H = 560, 240

NAVY = (11, 22, 49)
NAVY_2 = (30, 58, 138)
NAVY_SIDE = (7, 14, 33)
ORANGE = (234, 88, 12)
ORANGE_DARK = (194, 65, 12)
ORANGE_LIGHT = (251, 146, 60)
GREEN = (22, 163, 74)
BLUE = (2, 132, 199)
YELLOW = (250, 204, 21)
SKIN = (245, 200, 155)
SKIN_SIDE = (222, 168, 120)
HAIR = (92, 58, 33)
JEANS = (51, 65, 85)
JEANS_SIDE = (30, 41, 59)
SHOE = (15, 23, 42)
WHITE = (255, 255, 255)
BG = (255, 255, 255)
GROUND = (241, 245, 249)
CLOUD = (226, 232, 240)
PIECES = [ORANGE, GREEN, BLUE, YELLOW]


def font(size):
    for name in ('arialbd.ttf', 'DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'):
        try:
            return ImageFont.truetype(name, size * S)
        except OSError:
            continue
    return ImageFont.load_default()


def box(d, x, y, w, h, front, side=None, top=None, depth=6):
    """Blok: ön yüz + sağda koyu yan yüz + üstte açık yüz (hafif üç boyut)."""
    x, y, w, h, k = x * S, y * S, w * S, h * S, depth * S
    if top:
        d.polygon([(x, y), (x + k, y - k), (x + w + k, y - k), (x + w, y)], fill=top)
    if side:
        d.polygon([(x + w, y), (x + w + k, y - k), (x + w + k, y + h - k), (x + w, y + h)], fill=side)
    d.rectangle([x, y, x + w, y + h], fill=front)


def rect(d, x, y, w, h, fill):
    d.rectangle([x * S, y * S, (x + w) * S, (y + h) * S], fill=fill)


def rotated_block(d, cx, cy, w, h, angle, fill):
    """Omuzdan (cx, cy) aşağı sarkan, angle derece dönmüş kol bloğu."""
    a = math.radians(angle)
    pts = [(-w / 2, 0), (w / 2, 0), (w / 2, h), (-w / 2, h)]
    out = [((cx + px * math.cos(a) - py * math.sin(a)) * S, (cy + px * math.sin(a) + py * math.cos(a)) * S) for px, py in pts]
    d.polygon(out, fill=fill)
    return cx - h * math.sin(a), cy + h * math.cos(a)  # el ucu


def puzzle_piece(d, x, y, size, fill):
    """Yapboz parçası: kare + üstte ve sağda yuvarlak çıkıntı."""
    s = size
    rect(d, x, y, s, s, fill)
    r = s * 0.22
    d.ellipse([(x + s / 2 - r) * S, (y - r * 1.4) * S, (x + s / 2 + r) * S, (y + r * 0.6) * S], fill=fill)
    d.ellipse([(x + s - r * 0.6) * S, (y + s / 2 - r) * S, (x + s + r * 1.4) * S, (y + s / 2 + r) * S], fill=fill)


def head(d, x, y, blink=False):
    """Kare kafa (56×56): saç, turuncu şapka, gözler, gülümseme."""
    box(d, x, y, 56, 56, SKIN, SKIN_SIDE, None, 8)
    rect(d, x, y, 56, 14, HAIR)
    # şapka
    box(d, x - 2, y - 14, 60, 18, ORANGE, ORANGE_DARK, ORANGE_LIGHT, 8)
    rect(d, x + 30, y + 2, 34, 6, ORANGE_DARK)  # siperlik
    puzzle_piece(d, x + 8, y - 10, 9, YELLOW)
    # gözler
    if blink:
        rect(d, x + 12, y + 28, 10, 3, NAVY)
        rect(d, x + 34, y + 28, 10, 3, NAVY)
    else:
        rect(d, x + 12, y + 24, 10, 10, WHITE)
        rect(d, x + 16, y + 26, 6, 8, NAVY)
        rect(d, x + 34, y + 24, 10, 10, WHITE)
        rect(d, x + 38, y + 26, 6, 8, NAVY)
    # yanaklar ve ağız
    rect(d, x + 6, y + 38, 7, 4, (248, 160, 140))
    rect(d, x + 43, y + 38, 7, 4, (248, 160, 140))
    rect(d, x + 18, y + 42, 20, 5, (120, 53, 15))
    rect(d, x + 16, y + 39, 4, 5, (120, 53, 15))
    rect(d, x + 36, y + 39, 4, 5, (120, 53, 15))


def figure(d, x, y, wave_angle, blink=False, hold_left=False):
    """Tüm figür; (x, y) gövdenin sol üst köşesi."""
    # bacaklar
    box(d, x + 6, y + 58, 22, 40, JEANS, JEANS_SIDE, None, 5)
    box(d, x + 34, y + 58, 22, 40, JEANS, JEANS_SIDE, None, 5)
    rect(d, x + 4, y + 96, 26, 8, SHOE)
    rect(d, x + 32, y + 96, 26, 8, SHOE)
    # sol kol (aşağıda ya da kutu tutar)
    if hold_left:
        rect(d, x - 16, y + 4, 18, 40, NAVY_2)
        rect(d, x - 16, y + 42, 18, 12, SKIN)
    else:
        rect(d, x - 16, y + 4, 18, 48, NAVY_2)
        rect(d, x - 16, y + 50, 18, 12, SKIN)
    # gövde
    box(d, x, y, 62, 60, NAVY, NAVY_SIDE, None, 8)
    rect(d, x + 26, y, 10, 14, WHITE)  # yaka
    puzzle_piece(d, x + 10, y + 22, 13, YELLOW)  # rozet
    # sağ kol (el sallar)
    hx, hy = rotated_block(d, x + 71, y + 8, 18, 40, wave_angle, NAVY_2)
    a = math.radians(wave_angle)
    hand = [(-9, 0), (9, 0), (9, 13), (-9, 13)]
    d.polygon([((hx + px * math.cos(a) - py * math.sin(a)) * S, (hy + px * math.sin(a) + py * math.cos(a)) * S) for px, py in hand], fill=SKIN)
    head(d, x + 3, y - 58, blink)


def finish(img):
    return img.resize((W, H), Image.LANCZOS).quantize(colors=128, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)


def save(frames, path, duration):
    frames[0].save(path, save_all=True, append_images=frames[1:], duration=duration, loop=0, optimize=True, disposal=1)
    print(path, len(frames), 'kare', round(os.path.getsize(path) / 1024), 'KB')


def order_received(path):
    frames = []
    n = 24
    for i in range(n):
        t = i / n
        img = Image.new('RGB', (W * S, H * S), BG)
        d = ImageDraw.Draw(img)
        # zemin gölgesi
        d.ellipse([140 * S, 200 * S, 420 * S, 226 * S], fill=GROUND)
        # süzülen yapboz parçaları (döngüsel)
        for k in range(6):
            p = (t + k / 6) % 1
            px = [70, 470, 110, 500, 40, 430][k] + math.sin((p + k) * math.tau) * 8
            py = 200 - p * 170
            size = 12 + (k % 3) * 3
            if 0.05 < p < 0.95:
                puzzle_piece(d, px, py, size, PIECES[k % 4])
        bounce = abs(math.sin(t * math.tau)) * 4
        wave = -150 + math.sin(t * math.tau * 2) * 28
        figure(d, 175, 96 - bounce, wave, blink=(i in (10, 11)), hold_left=False)
        # Yapboz kutusu
        bx, by = 300, 128
        box(d, bx, by, 96, 72, ORANGE, ORANGE_DARK, ORANGE_LIGHT, 14)
        rect(d, bx + 40, by - 1, 16, 73, (253, 186, 116))  # bant
        puzzle_piece(d, bx + 14, by + 34, 18, WHITE)
        # onay rozeti (nabız)
        pulse = 1 + math.sin(t * math.tau) * 0.08
        cx, cy, r = bx + 104, by - 18, 22 * pulse
        d.ellipse([(cx - r - 4) * S, (cy - r - 4) * S, (cx + r + 4) * S, (cy + r + 4) * S], fill=(220, 252, 231))
        d.ellipse([(cx - r) * S, (cy - r) * S, (cx + r) * S, (cy + r) * S], fill=GREEN)
        d.line([((cx - r * 0.45) * S, cy * S), ((cx - r * 0.1) * S, (cy + r * 0.38) * S), ((cx + r * 0.5) * S, (cy - r * 0.35) * S)],
               fill=WHITE, width=int(6 * S * pulse), joint='curve')
        # parıltılar
        for k, (sx, sy) in enumerate([(cx - 34, cy - 20), (cx + 30, cy - 26), (cx + 36, cy + 16)]):
            on = math.sin((t + k / 3) * math.tau) > 0.2
            if on:
                ln = 6
                d.line([((sx - ln) * S, sy * S), ((sx + ln) * S, sy * S)], fill=YELLOW, width=3 * S)
                d.line([(sx * S, (sy - ln) * S), (sx * S, (sy + ln) * S)], fill=YELLOW, width=3 * S)
        frames.append(finish(img))
    save(frames, path, 80)


def van(d, x, y, wheel_angle, label_font):
    # kasa
    box(d, x, y, 190, 92, ORANGE, ORANGE_DARK, ORANGE_LIGHT, 14)
    rect(d, x, y + 74, 190, 6, ORANGE_DARK)
    d.text(((x + 95) * S, (y + 40) * S), 'YAPBOZ', font=label_font, fill=WHITE, anchor='mm')
    rect(d, x + 48, y + 56, 94, 18, WHITE)
    for k, c in enumerate(PIECES):
        puzzle_piece(d, x + 56 + k * 22, y + 60, 10, c)
    # kabin
    box(d, x + 190, y + 26, 76, 66, NAVY, NAVY_SIDE, NAVY_2, 14)
    rect(d, x + 204, y + 34, 50, 28, (186, 230, 253))  # cam
    # sürücü kafası (küçük)
    rect(d, x + 214, y + 40, 26, 22, SKIN)
    rect(d, x + 212, y + 34, 30, 9, ORANGE)
    rect(d, x + 232, y + 48, 4, 5, NAVY)
    rect(d, x + 266, y + 70, 8, 12, YELLOW)  # far
    rect(d, x - 4, y + 82, 282, 10, (51, 65, 85))  # tampon/şasi
    # tekerlekler
    for wx in (x + 50, x + 214):
        cy = y + 96
        d.ellipse([(wx - 22) * S, (cy - 22) * S, (wx + 22) * S, (cy + 22) * S], fill=SHOE)
        d.ellipse([(wx - 11) * S, (cy - 11) * S, (wx + 11) * S, (cy + 11) * S], fill=(148, 163, 184))
        for k in range(4):
            a = math.radians(wheel_angle + k * 90)
            d.line([(wx * S, cy * S), ((wx + math.cos(a) * 10) * S, (cy + math.sin(a) * 10) * S)], fill=SHOE, width=3 * S)


def shipped(path):
    frames = []
    n = 24
    label = font(26)
    for i in range(n):
        t = i / n
        img = Image.new('RGB', (W * S, H * S), BG)
        d = ImageDraw.Draw(img)
        # bulutlar (yavaş akar)
        for k, (cx0, cy, r) in enumerate([(80, 46, 18), (330, 30, 14), (520, 58, 16)]):
            cx = (cx0 - t * 70) % (W + 80) - 40
            for dx, dr in ((-r, 0.7), (0, 1), (r, 0.75)):
                rr = r * dr
                d.ellipse([(cx + dx - rr) * S, (cy - rr) * S, (cx + dx + rr) * S, (cy + rr) * S], fill=CLOUD)
        # yol
        rect(d, 0, 206, W, 34, GROUND)
        dash = 60
        off = (t * dash * 2) % dash
        for k in range(-1, W // dash + 2):
            rect(d, k * dash - off, 220, 30, 5, (203, 213, 225))
        bob = 2 if i % 2 else 0
        vx, vy = 130, 92 + bob
        # hız çizgileri ve toz
        for k in range(3):
            ln = 30 + ((i + k * 3) % 6) * 6
            rect(d, vx - 20 - ln, vy + 22 + k * 18, ln, 4, (203, 213, 225))
        for k in range(3):
            p = (t * 2 + k / 3) % 1
            r = 6 + p * 12
            px, py = vx - 10 - p * 60, 196 - p * 10
            shade = int(226 + p * 25)
            d.ellipse([(px - r) * S, (py - r) * S, (px + r) * S, (py + r) * S], fill=(shade, shade, min(255, shade + 4)))
        van(d, vx, vy, t * 360 * 2, label)
        frames.append(finish(img))
    save(frames, path, 70)


if __name__ == '__main__':
    out = sys.argv[1] if len(sys.argv) > 1 else '.'
    os.makedirs(out, exist_ok=True)
    order_received(os.path.join(out, 'siparis-alindi.gif'))
    shipped(os.path.join(out, 'kargoda.gif'))
