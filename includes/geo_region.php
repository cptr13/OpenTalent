<?php
/**
 * includes/geo_region.php
 *
 * Region inference helper for the script renderer.
 * - Primary target: United States (state abbrev or full state name)
 * - Falls back to metro -> state heuristics for common cities (e.g., "Atlanta" => GA)
 * - Graceful failure: returns null if no confident mapping
 *
 * Public API:
 *   infer_region_from_location(string $location): ?string
 *   infer_region_from_parts(?string $city, ?string $state, ?string $country): ?string
 *
 * Optional helpers:
 *   region_of_us_state(string $stateAbbr): ?string
 */

if (!defined('OT2_LOADED')) {
    define('OT2_LOADED', true);
}

/**
 * Infer region from separate (city, state, country) parts.
 * This is a thin wrapper that prefers explicit state when present,
 * then tries to resolve by city, and finally falls back to a joined
 * free-form string via infer_region_from_location().
 */
function infer_region_from_parts(?string $city, ?string $state, ?string $country): ?string
{
    $city    = trim((string)($city ?? ''));
    $state   = trim((string)($state ?? ''));
    $country = trim((string)($country ?? ''));

    // Quick country guards
    if ($country !== '') {
        $lc = mb_strtolower($country, 'UTF-8');
        if (preg_match('/\b(canada)\b/i', $lc)) {
            return 'Canada';
        }
        // If it's clearly not US/Canada and not empty, call it International
        if (!preg_match('/\b(us|usa|united states|u\.s\.a\.?)\b/i', $lc) && !preg_match('/\bcanada\b/i', $lc)) {
            return 'International';
        }
    }

    // 1) If we have a state part, try it directly (abbr or full name)
    if ($state !== '') {
        // Abbreviation first
        $abbr = extract_us_state_abbr($state);
        if ($abbr) {
            $region = region_of_us_state($abbr);
            if ($region) return $region;
        }
        // Full name next
        $abbrFromName = extract_us_state_from_full_name($state);
        if ($abbrFromName) {
            $region = region_of_us_state($abbrFromName);
            if ($region) return $region;
        }
    }

    // 2) If we didn't get a region yet, and we have a city, try city -> state
    if ($city !== '') {
        $abbrFromCity = state_from_known_metro($city);
        if ($abbrFromCity) {
            $region = region_of_us_state($abbrFromCity);
            if ($region) return $region;
        }
    }

    // 3) Fall back to a composed free-form string
    $raw = trim(implode(', ', array_filter([$city, $state, $country], fn($x) => $x !== '')));
    if ($raw !== '') {
        return infer_region_from_location($raw);
    }

    return null;
}

/**
 * Infer region name from a free-form location string.
 * Examples accepted:
 *   "Atlanta, GA", "Atlanta, Georgia", "123 Main St, Houston, TX 77001"
 *   "Boston, MA, USA", "Seattle WA", "Miami, Florida"
 *
 * Returns one of:
 *   "Northeast", "Mid-Atlantic", "Southeast", "Midwest",
 *   "Southwest", "Mountain West", "West Coast", "Alaska/Hawaii",
 *   or "Canada", "International", "United States" (generic), or null (unknown)
 */
function infer_region_from_location(string $location): ?string
{
    $loc = trim($location);
    if ($loc === '') return null;

    // Quick exits for special flags
    $l = mb_strtolower($loc, 'UTF-8');
    if (strpos($l, 'remote') !== false) {
        return 'Remote';
    }

    // Try country detection (very light)
    if (preg_match('/\bcanada\b/i', $loc)) {
        return 'Canada';
    }
    if (preg_match('/\b(usa|united states|u\.s\.a\.)\b/i', $loc)) {
        // keep going; we will try to find a state
    } elseif (preg_match('/\b(uk|united kingdom|england|scotland|wales|ireland)\b/i', $loc)) {
        return 'International';
    } elseif (preg_match('/\b(mexico|germany|france|spain|italy|india|china|japan|philippines|australia|brazil)\b/i', $loc)) {
        return 'International';
    }

    // 1) Look for explicit two-letter US state abbreviation
    $abbr = extract_us_state_abbr($loc);
    if ($abbr) {
        $region = region_of_us_state($abbr);
        if ($region) return $region;
    }

    // 2) Look for full US state name
    $abbrFromName = extract_us_state_from_full_name($loc);
    if ($abbrFromName) {
        $region = region_of_us_state($abbrFromName);
        if ($region) return $region;
    }

    // 3) Metro → state heuristics (best-effort)
    $abbrFromCity = state_from_known_metro($loc);
    if ($abbrFromCity) {
        $region = region_of_us_state($abbrFromCity);
        if ($region) return $region;
    }

    // 4) If we saw "USA" but no state, return generic
    if (preg_match('/\b(usa|united states|u\.s\.a\.)\b/i', $loc)) {
        return 'United States';
    }

    return null;
}

/**
 * Map a US state abbreviation to a region bucket.
 * Buckets are intentionally simple and sales-friendly.
 */
function region_of_us_state(string $stateAbbr): ?string
{
    $s = strtoupper(trim($stateAbbr));
    // Alaska/Hawaii
    if (in_array($s, ['AK','HI'], true)) {
        return 'Alaska/Hawaii';
    }

    // West Coast
    if (in_array($s, ['CA','OR','WA'], true)) {
        return 'West Coast';
    }

    // Mountain West
    if (in_array($s, ['AZ','CO','ID','MT','NV','NM','UT','WY'], true)) {
        return 'Mountain West';
    }

    // Southwest (TX/OK and neighbors we commonly treat sales-wise)
    if (in_array($s, ['TX','OK'], true)) {
        return 'Southwest';
    }

    // Midwest
    if (in_array($s, ['IL','IN','IA','KS','MI','MN','MO','ND','NE','OH','SD','WI'], true)) {
        return 'Midwest';
    }

    // Southeast
    if (in_array($s, ['AL','AR','FL','GA','KY','LA','MS','NC','SC','TN','VA','WV'], true)) {
        return 'Southeast';
    }

    // Mid-Atlantic
    if (in_array($s, ['DC','DE','MD','NJ','NY','PA'], true)) {
        return 'Mid-Atlantic';
    }

    // Northeast (classic New England)
    if (in_array($s, ['CT','MA','ME','NH','RI','VT'], true)) {
        return 'Northeast';
    }

    return null;
}

/**
 * Try to pull a two-letter state abbreviation from the string.
 */
function extract_us_state_abbr(string $loc): ?string
{
    static $abbrs = [
        'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
        'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
        'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
        'VA','WA','WV','WI','WY','DC'
    ];

    // Word boundary match (handles "Seattle, WA 98101" and "Austin TX")
    if (preg_match('/\b([A-Za-z]{2})\b/', $loc, $m)) {
        $candidate = strtoupper($m[1]);
        if (in_array($candidate, $abbrs, true)) {
            return $candidate;
        }
    }

    // Scan all tokens (more permissive; last match wins if multiple)
    $found = null;
    if (preg_match_all('/[A-Za-z]{2}/', $loc, $mm)) {
        foreach ($mm[0] as $tok) {
            $tokU = strtoupper($tok);
            if (in_array($tokU, $abbrs, true)) {
                $found = $tokU;
            }
        }
    }
    return $found;
}

/**
 * Try to find a full US state name and return its standard abbreviation.
 */
function extract_us_state_from_full_name(string $loc): ?string
{
    static $nameToAbbr = [
        'alabama'=>'AL','alaska'=>'AK','arizona'=>'AZ','arkansas'=>'AR','california'=>'CA',
        'colorado'=>'CO','connecticut'=>'CT','delaware'=>'DE','florida'=>'FL','georgia'=>'GA',
        'hawaii'=>'HI','idaho'=>'ID','illinois'=>'IL','indiana'=>'IN','iowa'=>'IA',
        'kansas'=>'KS','kentucky'=>'KY','louisiana'=>'LA','maine'=>'ME','maryland'=>'MD',
        'massachusetts'=>'MA','michigan'=>'MI','minnesota'=>'MN','mississippi'=>'MS','missouri'=>'MO',
        'montana'=>'MT','nebraska'=>'NE','nevada'=>'NV','new hampshire'=>'NH','new jersey'=>'NJ',
        'new mexico'=>'NM','new york'=>'NY','north carolina'=>'NC','north dakota'=>'ND',
        'ohio'=>'OH','oklahoma'=>'OK','oregon'=>'OR','pennsylvania'=>'PA','rhode island'=>'RI',
        'south carolina'=>'SC','south dakota'=>'SD','tennessee'=>'TN','texas'=>'TX','utah'=>'UT',
        'vermont'=>'VT','virginia'=>'VA','washington'=>'WA','west virginia'=>'WV','wisconsin'=>'WI',
        'wyoming'=>'WY','district of columbia'=>'DC','dc'=>'DC'
    ];

    $q = mb_strtolower($loc, 'UTF-8');
    // Match longest names first (e.g., "new hampshire" before "hampshire")
    $ordered = array_keys($nameToAbbr);
    usort($ordered, function($a,$b){ return mb_strlen($b,'UTF-8') <=> mb_strlen($a,'UTF-8'); });

    foreach ($ordered as $name) {
        // word boundary-like check; allow commas and spaces
        $pattern = '/\b' . preg_quote($name, '/') . '\b/i';
        if (preg_match($pattern, $q)) {
            return $nameToAbbr[$name];
        }
    }
    return null;
}

/**
 * Fallback: map common metro names to a state abbreviation.
 * (Lightweight list — add more as your data warrants.)
 */
function state_from_known_metro(string $loc): ?string
{
    static $metro = [
        // Southeast
        'atlanta'=>'GA','miami'=>'FL','orlando'=>'FL','tampa'=>'FL','jacksonville'=>'FL',
        'charlotte'=>'NC','raleigh'=>'NC','nashville'=>'TN','memphis'=>'TN','new orleans'=>'LA',
        'birmingham'=>'AL','greenville'=>'SC','charleston'=>'SC','richmond'=>'VA',
        // Mid-Atlantic
        'washington'=>'DC','dc'=>'DC','baltimore'=>'MD','philadelphia'=>'PA','pittsburgh'=>'PA',
        'newark'=>'NJ','jersey city'=>'NJ',
        // Northeast
        'boston'=>'MA','providence'=>'RI','hartford'=>'CT','new haven'=>'CT','manchester'=>'NH','portland, me'=>'ME',
        'new york'=>'NY','nyc'=>'NY','brooklyn'=>'NY','queens'=>'NY','buffalo'=>'NY','rochester'=>'NY','albany'=>'NY',
        // Midwest
        'chicago'=>'IL','detroit'=>'MI','grand rapids'=>'MI','minneapolis'=>'MN','st. paul'=>'MN','st paul'=>'MN',
        'st. louis'=>'MO','st louis'=>'MO','kansas city'=>'MO','milwaukee'=>'WI','madison'=>'WI',
        'columbus'=>'OH','cleveland'=>'OH','cincinnati'=>'OH','indianapolis'=>'IN','des moines'=>'IA','omaha'=>'NE',
        // Southwest / Mountain
        'dallas'=>'TX','fort worth'=>'TX','ft worth'=>'TX','houston'=>'TX','austin'=>'TX','san antonio'=>'TX','el paso'=>'TX',
        'oklahoma city'=>'OK','tulsa'=>'OK',
        'phoenix'=>'AZ','tucson'=>'AZ','albuquerque'=>'NM','santa fe'=>'NM',
        'denver'=>'CO','colorado springs'=>'CO','salt lake city'=>'UT','boise'=>'ID','las vegas'=>'NV',
        // West Coast / PNW
        'los angeles'=>'CA','la'=>'CA','san diego'=>'CA','san francisco'=>'CA','sf'=>'CA','san jose'=>'CA','sacramento'=>'CA','fresno'=>'CA',
        'seattle'=>'WA','tacoma'=>'WA','spokane'=>'WA','portland'=>'OR','eugene'=>'OR',
        // Alaska / Hawaii
        'anchorage'=>'AK','honolulu'=>'HI'
    ];

    $q = mb_strtolower($loc, 'UTF-8');
    // Try exact city tokens first (with commas)
    foreach ($metro as $city => $abbr) {
        if (strpos($q, $city) !== false) {
            return $abbr;
        }
    }

    // Fallback: split by comma and test first token as city
    $parts = array_map('trim', preg_split('/,|\n/', $q));
    if (!empty($parts)) {
        $first = $parts[0];
        if (isset($metro[$first])) {
            return $metro[$first];
        }
    }
    return null;
}
