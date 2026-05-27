import cv2
import pytesseract
import json
import sys
import os

from cleanUp import clean_ocr_text, split_schedules
from parser import parse_schedule

# Tesseract path configuration
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
    text = pytesseract.image_to_string(thresh, config=custom_config)

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