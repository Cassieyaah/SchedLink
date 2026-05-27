import cv2
import pytesseract
import json
import sys
import os

from cleanUp import clean_ocr_text, split_schedules
from parser import parse_schedule

# Tesseract installation path
pytesseract.pytesseract.tesseract_cmd = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe"
)

def run_ocr():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}, indent=4))
        return

    image_path = sys.argv[1]
    if not os.path.exists(image_path):
        print(json.dumps({"error": "Image file not found"}, indent=4))
        return

    img = cv2.imread(image_path)

    # Grayscale conversion
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Double the resolution scale to dramatically help Tesseract recognize small letters
    gray = cv2.resize(gray, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    
    # Target black text pixels cleanly using Gaussian Adaptive Thresholding
    thresh = cv2.adaptiveThreshold(
        gray,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        13,
        5
    )

    # PSM 4 looks across columns sequentially (essential for handling tables gracefully)
    custom_config = r'--oem 3 --psm 4'
    text = pytesseract.image_to_string(thresh, config=custom_config)

    cleaned_text = clean_ocr_text(text)
    schedules = split_schedules(cleaned_text)

    parsed_schedules = []
    for sched in schedules:
        parsed = parse_schedule(sched)
        if parsed:
            parsed_schedules.append(parsed)

    # Output valid JSON string payload to standard stream
    print(json.dumps(parsed_schedules, indent=4))

if __name__ == "__main__":
    run_ocr()