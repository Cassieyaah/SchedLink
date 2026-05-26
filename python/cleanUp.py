import re

def clean_ocr_text(text):
    text = text.upper()
    text = text.replace("\r", "\n")

    # Correct blatant Tesseract spelling errors on key subjects
    text = text.replace("BPSYSO", "BPSY80")
    text = text.replace("GNEDII", "GNED11")
    text = text.replace("ITERATURA", "LITERATURA")
    
    # Strip bad punctuation marks completely
    text = text.replace("‘", "").replace("’", "").replace("“", "").replace("”", "")
    text = re.sub(r"[|{}\[\]<>]", " ", text)
    
    return text

def split_schedules(text):
    raw_blocks = re.split(r'(?=20\d{7,8})', text)
    cleaned_blocks = []

    for block in raw_blocks:
        if not block.strip():
            continue
            
        lines = [line.strip() for line in block.split('\n') if line.strip()]
        combined_string = " ".join(lines)
        
        if "COURSE DESCRIPTION" in combined_string or "SCHED CODE" in combined_string:
            continue
            
        cleaned_blocks.append(combined_string)

    return cleaned_blocks