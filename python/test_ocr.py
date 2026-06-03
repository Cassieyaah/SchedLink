import pytesseract
from PIL import Image

# Tell Python where Tesseract is installed
pytesseract.pytesseract.tesseract_cmd = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe"
)

# Open image
img = Image.open(r"C:\xampp\htdocs\SchedLink\uploads\sample.png")

# Extract text
text = pytesseract.image_to_string(img)

print(text)