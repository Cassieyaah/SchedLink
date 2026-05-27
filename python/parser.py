import re

def parse_schedule(line):
    # Standardize casing and clear tricky layout punctuation symbols
    line = line.upper()
    line = re.sub(r'[-—_=+:]+', ' ', line)
    line = re.sub(r'\s+', ' ', line).strip()
    
    # 1. Isolate Schedule Code
    sched_match = re.search(r'\b(20\d{7,8})\b', line)
    if not sched_match:
        return None
    schedule_code = sched_match.group(1)
    working_line = line.replace(schedule_code, '').strip()

    # 2. Extract Course Code
    course_code = "UNKNOWN"
    known_prefixes = {"BIOL", "CHEM", "MATH", "SOIL", "GNED", "FITT", "BPSY", "CHBH", "COSC", "DCIT", "BEIT"}
    
    course_match = re.search(r'\b(BIOL|CHEM|MATH|SOIL|GNED|FITT|BPSY|CHBH|COSC|DCIT|BEIT)\s*(\d{1,3}[A-Z]?)\b', working_line)
    if course_match:
        course_code = f"{course_match.group(1)}{course_match.group(2)}"
        working_line = working_line.replace(course_match.group(0), "")
    else:
        generic_match = re.search(r'\b([A-Z]{2,4})\s*(\d{1,3}[A-Z]?)\b', working_line)
        if generic_match:
            course_code = f"{generic_match.group(1)}{generic_match.group(2)}"
            working_line = working_line.replace(generic_match.group(0), "")

    # 3. Flexible Time Pair Extraction (Handles variable spacings like "15 00 17 00" or "7 00 13 00")
    # Finds all numbers that look like hours or minutes
    all_numbers = re.findall(r'\b\d{1,4}\b', working_line)
    time_tokens = []
    
    idx = 0
    while idx < len(all_numbers):
        num = list(all_numbers)[idx]
        # If it's a 3 or 4 digit compact timestamp like 0700 or 1300
        if len(num) in [3, 4] and num.endswith("00"):
            val = int(num[:-2])
            if 7 <= val <= 22:
                time_tokens.append(f"{val}:00")
            working_line = re.sub(r'\b' + num + r'\b', '', working_line)
        # If it's a split pair like "15" followed by "00"
        elif idx + 1 < len(all_numbers) and all_numbers[idx+1] == "00":
            val = int(num)
            if 7 <= val <= 22:
                time_tokens.append(f"{val}:00")
                # Remove both values to protect description processing
                working_line = re.sub(r'\b' + num + r'\s+00\b', '', working_line)
            idx += 1
        idx += 1

    # Re-group individual times into clean Start-End Ranges
    time_pairs = []
    for t_idx in range(0, len(time_tokens) - 1, 2):
        if t_idx + 1 < len(time_tokens):
            time_pairs.append(f"{time_tokens[t_idx]}-{time_tokens[t_idx+1]}")

    # 4. Extract Days Safely (Normalizes Tesseract's "WW" glitch down to "W")
    days_found = []
    # Temporarily normalize "WW" tokens to individual "W" letters
    day_working = working_line.replace("WW", " W ")
    day_tokens = re.findall(r'\b(M|T|W|TH|F|S|THU|FRI|SAT|WED|MON|TUE)\b', day_working)
    
    for day in day_tokens:
        if day in ["M", "T", "W", "TH", "F", "S"]:
            days_found.append(day)
        elif day == "THU": days_found.append("TH")
        elif day == "FRI": days_found.append("F")
        elif day == "SAT": days_found.append("S")
        elif day == "WED": days_found.append("W")
        elif day == "MON": days_found.append("M")
        elif day == "TUE": days_found.append("T")

    # 5. Extract Room Identifiers
    known_rooms = {"AGSC", "CAS", "DCS", "CSPEAR", "GYM", "LH", "T100"}
    rooms_extracted = []
    
    room_tokens = re.findall(r'\b(AGSC|CAS|DCS|CSPEAR|GYM|LH|T100|\d{3}[A-Z]?)\b', working_line)
    for r_tok in room_tokens:
        if r_tok not in rooms_extracted and r_tok not in ["300", "200", "500"]:
            rooms_extracted.append(r_tok)

    # 6. Extract Units Column
    units = "3.00"
    if "FITT" in course_code or "FITNESS" in working_line:
        units = "2.00"
    else:
        unit_match = re.search(r'\b([1-5])[.,\s]*00\b', line)
        if unit_match:
            units = f"{unit_match.group(1)}.00"

    # 7. Build Clean Course Description
    description = working_line
    # Clear out all layout metadata keys to leave a pristine course title
    for scrap in list(known_rooms) + list(days_found) + ["300", "200", "500", "00", "WW"]:
        description = re.sub(r'\b' + re.escape(scrap) + r'\b', '', description, flags=re.IGNORECASE)
        
    description = re.sub(r'\b\d+\b', '', description) # Scrub remaining loose single digits
    description = re.sub(r'[^A-Z\s/&,]', '', description)
    description = re.sub(r'\s+', ' ', description).strip()

    if not description:
        description = "GENERAL ACADEMIC COURSE"

    # 8. Coordinate Meetings Layer Array Symmetric Mapping
    meetings = []
    total_meetings_count = max(len(time_pairs), len(days_found))
    if total_meetings_count == 0 and rooms_extracted:
        total_meetings_count = 1 # Fallback window for courses with rooms but missing schedules
        
    unified_room = " ".join(rooms_extracted).strip()

    for i in range(total_meetings_count):
        m_time = time_pairs[i] if i < len(time_pairs) else (time_pairs[-1] if time_pairs else "")
        m_day = days_found[i] if i < len(days_found) else (days_found[-1] if days_found else "")
        
        if m_time or m_day or unified_room:
            meetings.append({
                "time": m_time,
                "day": m_day,
                "room": unified_room
            })

    return {
        "schedule_code": schedule_code,
        "course_code": course_code,
        "description": description,
        "units": units,
        "meetings": meetings
    }