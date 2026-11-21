#!/usr/bin/env python3
"""Build the favicon set and social image from the round Tylilo logo.

Run from the repository root:
  python3 scripts/generate-icons.py
"""

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
LOGO = ROOT / "assets" / "img" / "logo-round.webp"
NAVY = (10, 49, 97, 255)
CREAM = (255, 246, 234, 255)
WHITE = (255, 255, 255, 255)


def load_logo() -> Image.Image:
    return Image.open(LOGO).convert("RGBA")


def fitted(logo: Image.Image, size: int, pad_ratio: float = 0.0, background=None) -> Image.Image:
    canvas = Image.new("RGBA", (size, size), background or (0, 0, 0, 0))
    pad = int(size * pad_ratio)
    inner = max(1, size - pad * 2)
    resized = logo.resize((inner, inner), Image.Resampling.LANCZOS)
    canvas.paste(resized, (pad, pad), resized)
    return canvas


def write_png(image: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, format="PNG", optimize=True)


def find_font(filename: str) -> Path:
    candidates = [
        Path("/tmp") / filename,
        ROOT / "assets" / "fonts" / filename,
    ]
    for path in candidates:
        if path.is_file():
            return path
    raise SystemExit(f"Missing {filename}. Download the Nunito static TTF before regenerating the social image.")


def social_card(logo: Image.Image) -> Image.Image:
    width, height = 1200, 630
    card = Image.new("RGBA", (width, height), NAVY)
    blobs = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    draw = ImageDraw.Draw(blobs)
    cream = (255, 246, 234, 22)
    draw.ellipse((860, -140, 1280, 280), fill=cream)
    draw.ellipse((-120, 360, 260, 740), fill=(255, 246, 234, 18))
    draw.ellipse((980, 430, 1160, 610), fill=(255, 246, 234, 16))
    card = Image.alpha_composite(card, blobs)

    badge = logo.resize((320, 320), Image.Resampling.LANCZOS)
    card.paste(badge, ((width - 320) // 2, 58), badge)

    title_font = ImageFont.truetype(str(find_font("Nunito-800.ttf")), 78)
    sub_font = ImageFont.truetype(str(find_font("Nunito-700.ttf")), 32)
    draw = ImageDraw.Draw(card)

    def center(text: str, font: ImageFont.FreeTypeFont, y: int, fill) -> None:
        box = font.getbbox(text)
        text_width = box[2] - box[0]
        draw.text(((width - text_width) / 2, y), text, font=font, fill=fill)

    center("Tylilo", title_font, 410, CREAM)
    center("A friendly little studio.", sub_font, 510, (246, 228, 200, 255))
    return card.convert("RGB")


def main() -> None:
    logo = load_logo()
    img = ROOT / "assets" / "img"

    for size, name in (
        (16, "favicon-16.png"),
        (32, "favicon-32.png"),
        (48, "favicon-48.png"),
        (192, "favicon-192.png"),
        (512, "favicon-512.png"),
    ):
        write_png(fitted(logo, size), img / name)

    write_png(fitted(logo, 180, background=WHITE), img / "apple-touch-icon.png")

    fitted(logo, 64).save(
        ROOT / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
    )

    social_card(logo).save(img / "og.png", format="PNG", optimize=True)
    print("Wrote favicons and assets/img/og.png")


if __name__ == "__main__":
    main()
