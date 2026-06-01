import moment from 'moment-timezone';
import store from '../store';
import { getBrowserTimezone } from './timezone';

export const DEFAULT_TIMEZONE = 'UTC';

export const getDisplayTimezone = () => {
  return store?.state?.currentUser?.user?.timezone || getBrowserTimezone() || DEFAULT_TIMEZONE;
};

const resolveMoment = (value) => {
  if (!value) {
    return null;
  }

  if (moment.isMoment(value)) {
    return value.clone();
  }

  const parsed = typeof value === 'string' ? moment.parseZone(value) : moment(value);

  return parsed.isValid() ? parsed : null;
};

export const formatInUserTimezone = (value, format) => {
  const parsed = resolveMoment(value);

  if (!parsed) {
    return typeof value === 'string' ? value : '';
  }

  return parsed.tz(getDisplayTimezone()).format(format);
};

export const formatCalendarInUserTimezone = (value) => {
  const parsed = resolveMoment(value);

  if (!parsed) {
    return typeof value === 'string' ? value : '';
  }

  return parsed.tz(getDisplayTimezone()).calendar();
};

export const toDateInUserTimezone = (value) => {
  const parsed = resolveMoment(value);

  if (!parsed) {
    return null;
  }

  return parsed.tz(getDisplayTimezone()).toDate();
};

export const toUtcIsoString = (value) => {
  const parsed = resolveMoment(value);

  if (!parsed) {
    return '';
  }

  return parsed.utc().toISOString();
};

export const combineDateAndTimeToUtcIso = (dateValue, timeValue, timezone = getDisplayTimezone()) => {
  const date = resolveMoment(dateValue);
  const time = resolveMoment(timeValue);

  if (!date || !time) {
    return '';
  }

  const zonedDate = date.tz(timezone);
  const zonedTime = time.tz(timezone);

  const combined = moment.tz(
    {
      year: zonedDate.year(),
      month: zonedDate.month(),
      day: zonedDate.date(),
      hour: zonedTime.hour(),
      minute: zonedTime.minute(),
      second: zonedTime.second(),
      millisecond: 0,
    },
    timezone,
  );

  return combined.isValid() ? combined.utc().toISOString() : '';
};

export const isFutureDateTime = (value) => {
  const parsed = resolveMoment(value);

  return !!parsed && parsed.isAfter(moment());
};
