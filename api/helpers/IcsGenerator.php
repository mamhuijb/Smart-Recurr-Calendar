<?php
/**
 * IcsGenerator — builds RFC 5545 compliant iCalendar (.ics) files
 * for appointment reminders. No external dependencies.
 *
 * Clients: Apple Calendar, Outlook, Google Calendar, Thunderbird
 */
class IcsGenerator {

    /**
     * Generate an ICS file content for a single appointment occurrence.
     *
     * @param array $params {
     *   @var string $uid        Unique ID (stable per event+date)
     *   @var string $summary    Event title
     *   @var string $description Plain-text description
     *   @var string $date       YYYY-MM-DD
     *   @var string $startTime  HH:MM (24h)
     *   @var string $endTime    HH:MM (24h)
     *   @var string $location   Free-text location (e.g. "Op locatie" or "Remote")
     *   @var string $timezone   IANA timezone (e.g. Europe/Amsterdam)
     *   @var string $organizerName  Organizer display name
     *   @var string $organizerEmail Organizer email
     *   @var string $attendeeName   Attendee display name
     *   @var string $attendeeEmail  Attendee email
     * }
     * @return string Full .ics file content
     */
    public static function build(array $params): string {
        $uid         = self::escape($params['uid'] ?? (bin2hex(random_bytes(8)) . '@smartrecur'));
        $summary     = self::escape($params['summary'] ?? 'Appointment');
        $description = self::escape($params['description'] ?? '');
        $date        = $params['date'] ?? date('Y-m-d');
        $startTime   = $params['startTime'] ?? '09:00';
        $endTime     = $params['endTime'] ?? '10:00';
        $location    = self::escape($params['location'] ?? '');
        $timezone    = $params['timezone'] ?? 'Europe/Amsterdam';
        $organizerName  = self::escape($params['organizerName'] ?? 'SmartRecur');
        $organizerEmail = $params['organizerEmail'] ?? 'noreply@smartrecur.local';
        $attendeeName   = self::escape($params['attendeeName'] ?? '');
        $attendeeEmail  = $params['attendeeEmail'] ?? '';

        // Validate time format and build DateTime objects in local timezone
        try {
            $tz = new \DateTimeZone($timezone);
            $start = new \DateTimeImmutable("{$date}T{$startTime}:00", $tz);
            $end   = new \DateTimeImmutable("{$date}T{$endTime}:00", $tz);
            // If end <= start (e.g., invalid config), add 1 hour to start
            if ($end <= $start) {
                $end = $start->modify('+1 hour');
            }
        } catch (\Exception $e) {
            // Fallback: all-day event on the given date
            $tz = new \DateTimeZone('UTC');
            $start = new \DateTimeImmutable("{$date}T09:00:00", $tz);
            $end   = new \DateTimeImmutable("{$date}T10:00:00", $tz);
        }

        // Convert to UTC for Z-suffixed UTC timestamps (most portable)
        $startUtc = $start->setTimezone(new \DateTimeZone('UTC'));
        $endUtc   = $end->setTimezone(new \DateTimeZone('UTC'));
        $nowUtc   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $fmt = 'Ymd\THis\Z';
        $dtStart   = $startUtc->format($fmt);
        $dtEnd     = $endUtc->format($fmt);
        $dtStamp   = $nowUtc->format($fmt);

        // Build ICS content. CRLF line endings are required by RFC 5545.
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//SmartRecur//Calendar//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:REQUEST';
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = "UID:{$uid}";
        $lines[] = "DTSTAMP:{$dtStamp}";
        $lines[] = "DTSTART:{$dtStart}";
        $lines[] = "DTEND:{$dtEnd}";
        $lines[] = "SUMMARY:{$summary}";
        if ($description !== '') {
            $lines[] = "DESCRIPTION:{$description}";
        }
        if ($location !== '') {
            $lines[] = "LOCATION:{$location}";
        }
        $lines[] = "ORGANIZER;CN={$organizerName}:mailto:{$organizerEmail}";
        if ($attendeeEmail !== '') {
            $cn = $attendeeName !== '' ? ";CN={$attendeeName}" : '';
            $lines[] = "ATTENDEE{$cn};RSVP=TRUE;PARTSTAT=NEEDS-ACTION:mailto:{$attendeeEmail}";
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'SEQUENCE:0';
        $lines[] = 'TRANSP:OPAQUE';
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'TRIGGER:-PT30M';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = "DESCRIPTION:{$summary}";
        $lines[] = 'END:VALARM';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // Fold long lines per RFC 5545 (max 75 octets)
        $folded = [];
        foreach ($lines as $line) {
            $folded[] = self::foldLine($line);
        }

        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * Build a stable UID from event ID + date so the same .ics updates
     * the previous one in the recipient's calendar (same UID = same event).
     */
    public static function buildUid(string $eventId, string $date, string $domain = 'smartrecur.local'): string {
        $safeDate = preg_replace('/[^0-9-]/', '', $date);
        $safeId   = preg_replace('/[^a-zA-Z0-9\-]/', '', $eventId);
        return "{$safeId}-{$safeDate}@{$domain}";
    }

    /**
     * RFC 5545 text escaping: backslash, comma, semicolon, newlines.
     */
    private static function escape(string $value): string {
        $value = str_replace(['\\', "\r\n", "\n", "\r", ',', ';'],
                             ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'],
                             $value);
        return $value;
    }

    /**
     * RFC 5545 line folding: lines longer than 75 octets are split with
     * CRLF followed by a single space (continuation).
     */
    private static function foldLine(string $line): string {
        if (strlen($line) <= 75) return $line;
        $chunks = [];
        $offset = 0;
        $len = strlen($line);
        while ($offset < $len) {
            $chunkLen = ($offset === 0) ? 75 : 74;
            $chunks[] = substr($line, $offset, $chunkLen);
            $offset += $chunkLen;
        }
        return implode("\r\n ", $chunks);
    }
}
