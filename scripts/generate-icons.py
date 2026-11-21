#!/usr/bin/env python3
"""Build Tylilo favicons from the hand-drawn T in assets/img/logo.svg.

The logo file is a Pixelmator SVG with the wordmark painted inside it.
This script isolates that painted T (it does not use a font), traces its
outline, and draws the mark in white on navy #0A3161.

Run from the repository root:

    python3 scripts/generate-icons.py
"""

from __future__ import annotations

import base64
import re
import struct
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
LOGO_SVG = ROOT / "assets" / "img" / "logo.svg"
IMG = ROOT / "assets" / "img"
NAVY = (10, 49, 97, 255)
WHITE = (255, 255, 255, 255)
# Rounded-square radius as a fraction of the icon size.
RADIUS = 0.22
# Tab icons: keep the T large enough to read at 16px.
TAB_PAD = 0.13
# Maskable safe zone is a circle whose diameter is 80% of the icon.
# A square glyph fits in that circle when each side is 0.8/sqrt(2).
MASK_PAD = (1 - (0.80 / (2 ** 0.5)) * 0.96) / 2


def load_logo_bitmap() -> Image.Image:
    svg = LOGO_SVG.read_text(encoding="utf-8")
    match = re.search(r'xlink:href="data:image/png;base64,([^"]+)"', svg)
    if not match:
        raise SystemExit(f"No embedded artwork found in {LOGO_SVG}")
    return Image.open(BytesIO(base64.b64decode(match.group(1)))).convert("RGBA")


def is_letter_pixel(r: int, g: int, b: int, a: int) -> bool:
    return a > 180 and r > 210 and g > 210 and b > 200


def extract_t_mask(logo: Image.Image) -> Image.Image:
    """Return a tight grayscale image of the hand-drawn T (white on black)."""
    width, height = logo.size
    pixels = logo.load()
    mask = bytearray(width * height)
    for y in range(height):
        row = y * width
        for x in range(width):
            if is_letter_pixel(*pixels[x, y]):
                mask[row + x] = 1

    seen = bytearray(width * height)
    components: list[tuple[int, int, list[int]]] = []
    for index, value in enumerate(mask):
        if not value or seen[index]:
            continue
        stack = [index]
        seen[index] = 1
        points = [index]
        min_x = index % width
        while stack:
            current = stack.pop()
            x = current % width
            y = current // width
            for ny in (y - 1, y, y + 1):
                if ny < 0 or ny >= height:
                    continue
                base = ny * width
                for nx in (x - 1, x, x + 1):
                    if nx < 0 or nx >= width:
                        continue
                    nxt = base + nx
                    if mask[nxt] and not seen[nxt]:
                        seen[nxt] = 1
                        stack.append(nxt)
                        points.append(nxt)
                        if nx < min_x:
                            min_x = nx
        components.append((min_x, len(points), points))

    if not components:
        raise SystemExit("No letter shapes found in the logo artwork.")

    # The word starts at the left. The leftmost shape is the T.
    _, _, points = min(components, key=lambda item: (item[0], -item[1]))
    xs = [point % width for point in points]
    ys = [point // width for point in points]
    min_x, max_x = min(xs), max(xs)
    min_y, max_y = min(ys), max(ys)
    crop = Image.new("L", (max_x - min_x + 1, max_y - min_y + 1), 0)
    crop_px = crop.load()
    for x, y in zip(xs, ys):
        crop_px[x - min_x, y - min_y] = 255
    return crop


def trace_outline(mask: Image.Image) -> list[tuple[float, float]]:
    """Trace the outer contour of a solid white shape."""
    width, height = mask.size
    src = mask.load()
    grid = bytearray((width + 2) * (height + 2))
    stride = width + 2
    for y in range(height):
        for x in range(width):
            if src[x, y] > 127:
                grid[(y + 1) * stride + (x + 1)] = 1

    start = None
    for y in range(1, height + 1):
        row = y * stride
        for x in range(1, width + 1):
            if grid[row + x]:
                start = (x, y)
                break
        if start:
            break
    if start is None:
        raise SystemExit("The T mask is empty.")

    # Clockwise Moore neighborhood. Search resumes two steps behind the step taken.
    directions = ((1, 0), (1, 1), (0, 1), (-1, 1), (-1, 0), (-1, -1), (0, -1), (1, -1))
    x, y = start
    direction = 6
    contour: list[tuple[int, int]] = []
    guard = 0
    limit = (width + 2) * (height + 2) * 2
    while guard < limit:
        guard += 1
        contour.append((x - 1, y - 1))
        found = False
        for step in range(8):
            nd = (direction + step) % 8
            nx = x + directions[nd][0]
            ny = y + directions[nd][1]
            if grid[ny * stride + nx]:
                direction = (nd + 6) % 8
                x, y = nx, ny
                found = True
                break
        if not found:
            break
        if (x, y) == start and len(contour) > 8:
            break
    return [(float(px), float(py)) for px, py in contour]


def rdp(points: list[tuple[float, float]], epsilon: float) -> list[tuple[float, float]]:
    if len(points) < 3:
        return points
    start, end = points[0], points[-1]
    dx = end[0] - start[0]
    dy = end[1] - start[1]
    length = (dx * dx + dy * dy) ** 0.5 or 1.0
    worst = 0.0
    index = 0
    for i, (x, y) in enumerate(points[1:-1], start=1):
        dist = abs(dy * x - dx * y + end[0] * start[1] - end[1] * start[0]) / length
        if dist > worst:
            worst = dist
            index = i
    if worst > epsilon:
        left = rdp(points[: index + 1], epsilon)
        right = rdp(points[index:], epsilon)
        return left[:-1] + right
    return [start, end]


def path_data(points: list[tuple[float, float]], scale: float, ox: float, oy: float) -> str:
    coords = []
    for x, y in points:
        coords.append((ox + x * scale, oy + y * scale))
    # Drop a closing duplicate if the tracer returned to its start.
    if len(coords) > 2 and abs(coords[0][0] - coords[-1][0]) < 0.01 and abs(coords[0][1] - coords[-1][1]) < 0.01:
        coords = coords[:-1]
    parts = [f"M{coords[0][0]:.2f} {coords[0][1]:.2f}"]
    for x, y in coords[1:]:
        parts.append(f"L{x:.2f} {y:.2f}")
    parts.append("Z")
    return "".join(parts)


def layout(mask_size: tuple[int, int], canvas: float, pad: float) -> tuple[float, float, float]:
    inner = canvas * (1 - 2 * pad)
    scale = min(inner / mask_size[0], inner / mask_size[1])
    drawn_w = mask_size[0] * scale
    drawn_h = mask_size[1] * scale
    return scale, (canvas - drawn_w) / 2, (canvas - drawn_h) / 2


def build_svg(points: list[tuple[float, float]], mask_size: tuple[int, int], canvas: int, pad: float, rounded: bool) -> str:
    scale, ox, oy = layout(mask_size, canvas, pad)
    d = path_data(points, scale, ox, oy)
    radius = canvas * RADIUS if rounded else 0
    shape = f'<rect width="{canvas}" height="{canvas}" rx="{radius:.2f}" fill="#0A3161"/>'
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {canvas} {canvas}">\n'
        "  <!-- T outline traced from the painted wordmark in assets/img/logo.svg -->\n"
        f"  {shape}\n"
        f'  <path fill="#FFFFFF" d="{d}"/>\n'
        "</svg>\n"
    )


def rasterize(points: list[tuple[float, float]], mask_size: tuple[int, int], size: int, pad: float, rounded: bool) -> Image.Image:
    """Draw at 8x and downsample so the thin handwritten stroke survives at 16px."""
    scale_up = 8
    big = size * scale_up
    image = Image.new("RGBA", (big, big), (0, 0, 0, 0) if rounded else NAVY)
    draw = ImageDraw.Draw(image)
    radius = int(round(big * RADIUS)) if rounded else 0
    if rounded:
        draw.rounded_rectangle((0, 0, big - 1, big - 1), radius=radius, fill=NAVY)
    else:
        draw.rectangle((0, 0, big - 1, big - 1), fill=NAVY)
    scale, ox, oy = layout(mask_size, big, pad)
    polygon = [(ox + x * scale, oy + y * scale) for x, y in points]
    draw.polygon(polygon, fill=WHITE)
    return image.resize((size, size), Image.Resampling.LANCZOS)


def save_ico(images: list[Image.Image], path: Path) -> None:
    """Write a PNG-compressed ICO containing each image's exact pixels."""
    blobs = []
    for image in images:
        buffer = BytesIO()
        image.save(buffer, format="PNG", optimize=True)
        blobs.append(buffer.getvalue())
    with path.open("wb") as handle:
        handle.write(struct.pack("<HHH", 0, 1, len(images)))
        offset = 6 + 16 * len(images)
        for image, blob in zip(images, blobs):
            width = image.width if image.width < 256 else 0
            height = image.height if image.height < 256 else 0
            handle.write(struct.pack("<BBBBHHII", width, height, 0, 0, 1, 32, len(blob), offset))
            offset += len(blob)
        for blob in blobs:
            handle.write(blob)


def main() -> None:
    mask = extract_t_mask(load_logo_bitmap())
    raw = trace_outline(mask)
    points = rdp(raw, 1.15)
    if points[0] != points[-1]:
        points.append(points[0])
    print(f"Traced T: {mask.size[0]}x{mask.size[1]}px, {len(raw)} contour points, {len(points)} after simplifying")

    svg = build_svg(points, mask.size, 64, TAB_PAD, rounded=True)
    (IMG / "favicon.svg").write_text(svg, encoding="utf-8")

    tab_sizes = {
        16: IMG / "favicon-16.png",
        32: IMG / "favicon-32.png",
        48: IMG / "favicon-48.png",
    }
    tab_images = []
    for size, path in tab_sizes.items():
        image = rasterize(points, mask.size, size, TAB_PAD, rounded=True)
        image.save(path, format="PNG", optimize=True)
        tab_images.append(image)
    save_ico(tab_images, ROOT / "favicon.ico")

    for size, name in ((192, "favicon-192.png"), (512, "favicon-512.png")):
        rasterize(points, mask.size, size, MASK_PAD, rounded=False).save(IMG / name, format="PNG", optimize=True)

    rasterize(points, mask.size, 180, MASK_PAD, rounded=False).save(IMG / "apple-touch-icon.png", format="PNG", optimize=True)

    # Large master used while checking the trace. Not part of the site set.
    rasterize(points, mask.size, 512, TAB_PAD, rounded=True).save("/tmp/favicon-rounded-512.png", format="PNG")
    mask.save("/tmp/t-mask.png")
    print("Wrote favicon.svg, png sizes, apple-touch-icon, and favicon.ico")


if __name__ == "__main__":
    main()
