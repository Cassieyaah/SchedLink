import cv2
import pytesseract
import json
import sys
import os
import shutil

from cleanUp import clean_ocr_text, split_schedules
from parser import parse_schedule

def configure_tesseract():
    candidates = [
        os.environ.get("TESSERACT_CMD", ""),
        r"C:\Program Files\Tesseract-OCR\tesseract.exe",
        r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
        r"C:\Users\laptop\AppData\Local\Programs\Tesseract-OCR\tesseract.exe",
        shutil.which("tesseract") or "",
    ]

    for candidate in candidates:
        if candidate and os.path.exists(candidate):
            pytesseract.pytesseract.tesseract_cmd = candidate
            return True

    return False

def run_ocr():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}, indent=4))
        return

    if not configure_tesseract():
        print(json.dumps({
            "error": "Tesseract OCR is not installed or could not be found. Install Tesseract, then set TESSERACT_CMD to the full tesseract.exe path if it is not installed in Program Files."
        }, indent=4))
        return

    image_path = sys.argv[1]
    if not os.path.exists(image_path):
        print(json.dumps({"error": "Image file not found"}, indent=4))
        return

    img = cv2.imread(image_path)
    if img is None:
        print(json.dumps({"error": "Uploaded file could not be read as an image."}, indent=4))
        return

    # Convert to grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Scale up text resolution
    gray = cv2.resize(gray, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    
    # Apply binarization filtering
    thresh = cv2.adaptiveThreshold(
        gray,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        13,
        5
    )

    # Process via sequential column layout mode
    custom_config = r'--oem 3 --psm 4'
    try:
        text = pytesseract.image_to_string(thresh, config=custom_config)
    except pytesseract.pytesseract.TesseractNotFoundError:
        print(json.dumps({
            "error": "Tesseract OCR executable was not found. Check the Tesseract installation path."
        }, indent=4))
        return

    cleaned_text = clean_ocr_text(text)
    schedules = split_schedules(cleaned_text)

    parsed_schedules = []
    for sched in schedules:
        parsed = parse_schedule(sched)
        if parsed:
            parsed_schedules.append(parsed)

    # Return pure JSON formatting to backend controller
    print(json.dumps(parsed_schedules, indent=4))

if __name__ == "__main__":
    run_ocr()
