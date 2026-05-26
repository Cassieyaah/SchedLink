import re

def parse_schedule(line):
    # Normalize noise right away
    line = re.sub(r'[-—_=+]+', ' ', line)
    line = re.sub(r'\s+', ' ', line).strip()
    
    # 1. Isolate Schedule Code
    sched_match = re.search(r'\b(20\d{7,8})\b', line)
    if not sched_match:
        return None
    schedule_code = sched_match.group(1)
    
    working_line = line.replace(schedule_code, '').strip()

    # 2. Extract Valid Time Blocks (e.g., "13:00-14:00")
    time_pairs = re.findall(r'\b(\d{1,2}:\d{2})\s*[-]*\s*(\d{1,2}:\d{2})\b', working_line)
    
    # Strip times out of working line to avoid polluting description fields
    for t_pair in re.findall(r'\b\d{1,2}:\d{2}\b', working_line):
        working_line = working_line.replace(t_pair, '')

    # 3. Smart Course Code Extraction Pass (Handles spaces like "GNED 08" or "COSC 6SA")
    course_code = "UNKNOWN"
    # Match known prefixes followed by optional spaces, then numbers/letters
    course_pattern = r'\b(GNED|COSC|DCIT|MATH|BEIT|FITT|BPSY|CHBH)\s*([0-9A-Z]{1,4})\b'
    course_match = re.search(course_pattern, working_line)
    
    if course_match:
        prefix = course_match.group(1)
        suffix = course_match.group(2)
        course_code = f"{prefix}{suffix}"
        # Remove the matched course code from the working line
        working_line = working_line.replace(course_match.group(0), '')
    else:
        # Fallback regex for any unknown 2-4 letter prefix separated by a space and a number
        fallback_match = re.search(r'\b([A-Z]{2,4})\s+(\d{1,3})\b', working_line)
        if fallback_match:
            course_code = f"{fallback_match.group(1)}{fallback_match.group(2)}"
            working_line = working_line.replace(fallback_match.group(0), '')

    # 4. Token Classification Engine for Units, Days, and Rooms
    tokens = working_line.split()
    
    units = "3.00"  # Default standard fallback
    days_found = []
    rooms_found = []
    description_words = []

    valid_days = {"M", "T", "W", "F", "S", "TH", "THU", "FRI", "SAT", "WED", "MON", "TUE", "WW"}

    for token in tokens:
        token_clean = token.strip().upper().replace(".", "").replace(",", "")
        if not token_clean:
            continue

        # Check for Units Column
        if re.match(r'^[1-5]00$', token_clean) or re.match(r'^[1-5][.,]00$', token):
            units = f"{token_clean[0]}.00"
            continue
        elif token_clean in ['200', '300', '500']:
            units = f"{token_clean[0]}.00"
            continue

        # Check for Day Labels
        if token_clean in valid_days:
            normalized_day = "W" if token_clean == "WW" else token_clean
            days_found.append(normalized_day)
            continue

        # Check for Room Labels
        if "LH" in token_clean or token_clean == "GYM" or (token_clean.isdigit() and len(token_clean) == 3):
            rooms_found.append(token_clean)
            continue

        # Build description list
        description_words.append(token)

    # 5. Build and Deep-Clean Course Description
    description = " ".join(description_words).strip()
    description = re.sub(r'[^A-Z0-9\s/&,.]', '', description.upper())
    
    # Strip annoying OCR artifact tails (e.g., loose ending numbers/letters like " 8", " 1")
    description = re.sub(r'\s+[0-9A-Z]\b$', '', description)
    description = re.sub(r'\s+[0-9A-Z]\b$', '', description) # Double pass to catch stacked artifacts
    
    description = re.sub(r'\s+', ' ', description).strip()

    # Final backup check for units context
    if units == "3.00" and ("FITNESS" in description or "FITT" in course_code):
        units = "2.00"

    # Assemble Meetings array dynamically
    meetings = []
    total_meetings_count = max(len(time_pairs), len(days_found))
    
    for i in range(total_meetings_count):
        m_time = ""
        if i < len(time_pairs):
            m_time = f"{time_pairs[i][0]}-{time_pairs[i][1]}"
        elif time_pairs:
            m_time = f"{time_pairs[-1][0]}-{time_pairs[-1][1]}"

        m_day = days_found[i] if i < len(days_found) else (days_found[-1] if days_found else "")
        m_room = rooms_found[i] if i < len(rooms_found) else (rooms_found[-1] if rooms_found else "")
        
        if m_day in ["A", "I", "E", "O", "X"]:
            m_day = ""

        if m_time or m_day:
            meetings.append({
                "time": m_time,
                "day": m_day,
                "room": m_room
            })

    if not description:
        description = "UNKNOWN COURSE DESCRIPTION"

    return {
        "schedule_code": schedule_code,
        "course_code": course_code,
        "description": description,
        "units": units,
        "meetings": meetings
    }