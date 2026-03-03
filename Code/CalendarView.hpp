#pragma once

#include <string>
#include <vector>

namespace emr {

struct TimeSlot {
    std::string time;
    int hour;
    int minutes;
    bool available;
    std::string date;

    TimeSlot() = default;
    TimeSlot(const std::string &t, int h, int m, bool avail, const std::string &d);
};

class CalendarView {
public:
    CalendarView(int start = 8, int end = 17, int slotDuration = 30);

    std::vector<TimeSlot> generateTimeSlots() const;
    std::string formatTime(int hour, int minutes) const;

    // date should be in "YYYY-MM-DD" format
    std::vector<TimeSlot> renderCalendar(const std::string &date) const;

    // returns true if booking succeeded and sets message accordingly
    bool bookSlot(const std::string &date,
                  const std::string &slotTime,
                  std::string &message);

private:
    int workingHoursStart;
    int workingHoursEnd;
    int slotDurationMinutes;
};

} // namespace emr
