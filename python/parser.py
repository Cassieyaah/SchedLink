import re

def parse_schedule(line):

    pattern = r"^(\d+)\s+([A-Z]+\s*\d+[A-Z]*)\s+(.*?)\s+(\d+\.\d+)"

    match = re.search(pattern, line)

    if match:

        return {
            "schedule_code": match.group(1),
            "course_code": match.group(2),
            "description": match.group(3),
            "units": match.group(4),
            "meetings": extract_meetings(line)
        }

    return None

def extract_meetings(line):
    meetings = []

    for time, day in matches:

        meetings.append({
            "time": time,
            "day": day,
            "valid": is_valid_time_range(time)
        })

    return meetings

def is_valid_time_range(time_range):

    try:

        start, end = time_range.split("-")

        start_time = datetime.strptime(start, "%H:%M")
        end_time = datetime.strptime(end, "%H:%M")

        return end_time > start_time

    except:
        return False