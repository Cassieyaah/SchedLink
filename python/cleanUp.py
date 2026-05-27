import re

def clean_ocr_text(text):
    # Standardize casing for unified parsing operations
    text = text.upper()
    text = text.replace("\r", "\n")

    # 1. Structural Fixes for merged layout text artifacts
    text = text.replace("DCITSSA", "DCIT ")
    text = text.replace("COSC SA", "COSC ")
    text = text.replace("BPSYSO", "BPSY80")
    text = text.replace("GNEDII", "GNED11")
    text = text.replace("ITERATURA", "LITERATURA")
    text = text.replace("ANALYTIC CHEMISTRY", "ANALYTICAL CHEMISTRY")
    
    # 2. De-glitcher: Separates merged day-time tokens (e.g., WW10 -> W 10, TH17 -> TH 17)
    text = re.sub(r'\b(WW|TH|M|T|W|F|S)(\d{2})\b', r'\1 \2', text)
    
    # 3. Strip layout noise and punctuation markers
    text = text.replace("‘", "").replace("’", "").replace("“", "").replace("”", "")
    text = re.sub(r"[|{}\[\]<>]", " ", text)
    
    return text

def split_schedules(text):
    # Slice the raw text string into blocks using the 9-digit schedule codes
    raw_blocks = re.split(r'(?=20\d{7,8})', text)
    cleaned_blocks = []

    for block in raw_blocks:
        if not block.strip():
            continue
            
        lines = [line.strip() for line in block.split('\n') if line.strip()]
        combined_string = " ".join(lines)
        
        # Drop header lines completely
        if "COURSE DESCRIPTION" in combined_string or "SCHED CODE" in combined_string:
            continue
            
        cleaned_blocks.append(combined_string)

    return cleaned_blocks