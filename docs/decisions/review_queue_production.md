# Cover queue schdulat edge cases

## Medium
4. Meeting started/ended jobs update the sent flag after Notification::send() and swallow update failures. Worker death after send, or DB update failure, can duplicate meeting notifications later. See [SendMeetingStartedNotification.php (line 59)](C:/Users/Hamza/daywright/app/Jobs/SendMeetingStartedNotification.php:59), [SendMeetingEndedNotification.php (line 59)](C:/Users/Hamza/daywright/app/Jobs/SendMeetingEndedNotification.php:59).



