import re


def clean_ocr_text(text):

    # Remove weird symbols
    text = re.sub(r"[|{}[\]<>]", "", text)

    # Normalize spaces but preserve line breaks
    text = re.sub(r"[ \t]+", " ", text)

    # Fix common OCR mistakes
    replacements = {
        "Ww": "W",
        "ww": "W",
        "Ss": "S",
        "ss": "S",        
        "Tt": "T",
        "THH": "TH",
        "Th": "TH",
        "tH": "TH",
    }

    for wrong, correct in replacements.items():
        text = text.replace(wrong, correct)

    # Fix common course code OCR mistakes
    course_fixes = {
        "DCcIT": "DCIT",
        "DcIT": "DCIT",
        "DClT": "DCIT",
        "pciTas": "DCIT25",
    }

    for wrong, correct in course_fixes.items():
        text = text.replace(wrong, correct)
    
    text = text.upper()
    return text


def merge_multiline_schedules(text):

    lines = text.splitlines()

    merged_lines = []
    current_line = ""

    for line in lines:

        line = line.strip()

        if not line:
            continue

        # If line starts with schedule code
        if re.match(r"^\d{9}", line):

            # Save previous schedule
            if current_line:
                merged_lines.append(current_line)

            current_line = line

        else:
            # Continue previous schedule
            current_line += " " + line

    # Add final schedule
    if current_line:
        merged_lines.append(current_line)

    return merged_lines