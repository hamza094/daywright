import moment from 'moment';

export function calculateRemainingTime(task, currentDate) {
  if (task.due_at_utc !== null) {
    const dueDate = moment.utc(task.due_at_utc);
    const now = moment.utc(currentDate);

    const duration = moment.duration(dueDate.diff(now));

    if (duration.asMilliseconds() <= 0) {
      return 'Due date passed';
    }

    const days = duration.days();
    const hours = duration.hours();
    const minutes = duration.minutes();

    const messageParts = [];
    if (days > 0) {
      messageParts.push(`${days} day(s)`);
    }
    if (hours > 0) {
      messageParts.push(`${hours} hour(s)`);
    }
    if (minutes > 0) {
      messageParts.push(`${minutes} minute(s)`);
    }

    return `${messageParts.join(', ')} remaining`;
  }
}

export function url($slug, $id) {
  return '/projects/' + $slug + '/tasks/' + $id;
}
