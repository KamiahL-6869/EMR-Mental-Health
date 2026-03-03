#include "CalendarView.hpp"

#include <iomanip>
#include <sstream>

namespace emr {

// --- TimeSlot struct ----------------------------------------------------------------

TimeSlot::TimeSlot(const std::string &t,
                   int h,
                   int m,
                   bool avail,
                   const std::string &d)
    : time(t), hour(h), minutes(m), available(avail), date(d) {}

// --- CalendarView implementation -----------------------------------------------------

CalendarView::CalendarView(int start, int end, int slotDuration)
    : workingHoursStart(start),
      workingHoursEnd(end),
      slotDurationMinutes(slotDuration) {}

std::vector<TimeSlot> CalendarView::generateTimeSlots() const {
    std::vector<TimeSlot> slots;
    int currentHour = workingHoursStart;
    int currentMinutes = 0;

    while (currentHour < workingHoursEnd) {
        const std::string timeString = formatTime(currentHour, currentMinutes);
        slots.emplace_back(timeString, currentHour, currentMinutes, true, "");

        currentMinutes += slotDurationMinutes;
        if (currentMinutes >= 60) {
            currentMinutes = 0;
            ++currentHour;
        }
    }

    return slots;
}

std::string CalendarView::formatTime(int hour, int minutes) const {
    const char *period = (hour >= 12) ? "PM" : "AM";
    int displayHour = hour > 12 ? hour - 12 : (hour == 0 ? 12 : hour);

    std::ostringstream out;
    out << displayHour << ':' << std::setw(2) << std::setfill('0') << minutes << ' '
        << period;
    return out.str();
}

std::vector<TimeSlot> CalendarView::renderCalendar(const std::string &date) const {
    std::vector<TimeSlot> slots = generateTimeSlots();
    for (auto &slot : slots) {
        slot.date = date; // date should already be formatted as YYYY-MM-DD
    }
    return slots;
}

bool CalendarView::bookSlot(const std::string &date,
                            const std::string &slotTime,
                            std::string &message) {
    std::vector<TimeSlot> slots = renderCalendar(date);
    for (auto &slot : slots) {
        if (slot.time == slotTime) {
            if (!slot.available) {
                message = "Slot not available";
                return false;
            }
            slot.available = false;
            message = "Appointment booked at " + slotTime;
            return true;
        }
    }
    message = "Slot not found";
    return false;
}

} // namespace emr
