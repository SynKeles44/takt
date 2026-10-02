/**
 * Hour and minute as two fields, so typing moves on by itself.
 *
 * The native `input[type=time]` keeps its segments in the shadow DOM: a browser that does not
 * advance after a digit cannot be made to, and the browsers disagree — Chromium advances after
 * "7" and waits after "1", Safari does not advance at all. Two real inputs behave the same
 * everywhere.
 *
 * When to move on is the whole design. After two digits, obviously. After ONE digit only when a
 * second one could not follow: "7" can only be 07, while "1" could still become 14, so the hour
 * waits there. Jumping on every first digit would make 10 to 23 untypeable, which is a worse
 * problem than the one being solved.
 */
const pad = (value) => (value === '' ? '' : String(Math.min(99, Math.max(0, Number(value)))).padStart(2, '0'));

export function timeFields() {
    const sync = (field) => {
        const hour = field.querySelector('[data-time-hour]');
        const minute = field.querySelector('[data-time-minute]');
        const mirror = field.querySelector('[data-time-value]');

        // half a time is not a time: the mirror stays empty until both halves are there
        mirror.value = hour.value === '' || minute.value === ''
            ? ''
            : `${pad(hour.value)}:${pad(minute.value)}`;
    };

    const digitsOnly = (input, max) => {
        const cleaned = input.value.replace(/\D/g, '').slice(0, 2);

        input.value = cleaned === '' ? '' : String(Math.min(max, Number(cleaned)));

        return cleaned;
    };

    document.addEventListener('input', (event) => {
        const input = event.target;
        const field = input.closest?.('[data-time-field]');

        if (! field) return;

        const isHour = input.hasAttribute('data-time-hour');
        const digits = digitsOnly(input, isHour ? 23 : 59);

        sync(field);

        if (! isHour) return;

        // two digits, or a first digit no second one can follow — 3 through 9 are hours on their own
        if (digits.length === 2 || (digits.length === 1 && Number(digits) > 2)) {
            const minute = field.querySelector('[data-time-minute]');

            input.value = pad(input.value);
            sync(field);
            minute.focus();
            minute.select();
        }
    });

    document.addEventListener('keydown', (event) => {
        const input = event.target;
        const field = input.closest?.('[data-time-field]');

        if (! field) return;

        // backspace at the start of the minute goes back to the hour, the way one field would
        if (event.key === 'Backspace' && input.hasAttribute('data-time-minute') && input.value === '') {
            const hour = field.querySelector('[data-time-hour]');

            event.preventDefault();
            hour.focus();
            hour.setSelectionRange(hour.value.length, hour.value.length);

            return;
        }

        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;

        const max = input.hasAttribute('data-time-hour') ? 23 : 59;
        const step = event.key === 'ArrowUp' ? 1 : -1;

        event.preventDefault();
        input.value = pad(String(((Number(input.value || 0) + step) % (max + 1) + max + 1) % (max + 1)));
        sync(field);
    });

    // leaving a half-typed half completes it, so "7" reads as 07 rather than as a mistake
    document.addEventListener('focusout', (event) => {
        const input = event.target;
        const field = input.closest?.('[data-time-field]');

        if (! field) return;

        input.value = pad(input.value);
        sync(field);
    });
}
