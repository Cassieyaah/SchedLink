import cv2
import pytesseract
import json
import sys
from PIL import Image
from cleanUp import clean_ocr_text, merge_multiline_schedules
from parser import parse_schedule

# Tesseract path
pytesseract.pytesseract.tesseract_cmd = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe"
)

# Load image
image_path = sys.argv[1]

img = cv2.imread(image_path)

# Convert to grayscale
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)


# Enlarge image
gray = cv2.resize(gray, None, fx=2, fy=2)

# Thresholding
_, thresh = cv2.threshold(gray, 150, 255, cv2.THRESH_BINARY)

# OCR config
custom_config = r'--oem 3 --psm 6'

# Extract text
text = pytesseract.image_to_string(thresh, config=custom_config)

cleaned_text = clean_ocr_text(text)
merged_schedules = merge_multiline_schedules(cleaned_text)
parsed_schedules = []

for sched in merged_schedules:

    parsed = parse_schedule(sched)

    if parsed:
        parsed_schedules.append(parsed)

print(json.dumps(parsed_schedules, indent=4))