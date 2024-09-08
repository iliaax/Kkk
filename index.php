<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

// Конфигурация Spotify API
$session = new SpotifyWebAPI\Session(
    '11b151dcf3fb4d2380409135017ba999',
    '720013f127454dc581a1675a5dbcdc29'
);

$api = new SpotifyWebAPI\SpotifyWebAPI();

try {
    $session->requestCredentialsToken();
    $accessToken = $session->getAccessToken();
    $api->setAccessToken($accessToken);
} catch (Exception $e) {
    die("Error getting access token: " . $e->getMessage());
}

// Function to get user's country based on IP
function getUserCountry() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));
    return $details->country ?? 'US';
}

// List of Spotify-supported countries
$spotifyCountries = [
    'US' => 'United States', 'GB' => 'United Kingdom', 'AD' => 'Andorra', 'AR' => 'Argentina', 'AU' => 'Australia',
    'AT' => 'Austria', 'BE' => 'Belgium', 'BO' => 'Bolivia', 'BR' => 'Brazil', 'BG' => 'Bulgaria', 'CA' => 'Canada',
    'CL' => 'Chile', 'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
    'DK' => 'Denmark', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'SV' => 'El Salvador', 'EE' => 'Estonia',
    'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece', 'GT' => 'Guatemala', 'HN' => 'Honduras',
    'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IE' => 'Ireland', 'IT' => 'Italy', 'JP' => 'Japan',
    'LV' => 'Latvia', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MY' => 'Malaysia',
    'MT' => 'Malta', 'MX' => 'Mexico', 'MC' => 'Monaco', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
    'NI' => 'Nicaragua', 'NO' => 'Norway', 'PA' => 'Panama', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
    'PL' => 'Poland', 'PT' => 'Portugal', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'ES' => 'Spain', 'SE' => 'Sweden',
    'CH' => 'Switzerland', 'TW' => 'Taiwan', 'TR' => 'Turkey', 'UY' => 'Uruguay'
];

$userCountry = getUserCountry();
$selectedCountry = $_GET['country'] ?? $userCountry;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

function getParentGenres($genres) {
    $highlightedGenres = [
        'russian dance', 'russian hip hop', 'russian viral rap', 'australian electropop', 'indietronica',
        'israeli techno', 'big beat', 'downtempo', 'electronica', 'trip hop', 'alternative rock',
        'new wave', 'noise pop', 'post-punk', 'scottish indie', 'scottish shoegaze', 'shoegaze',
        'singer-songwriter', 'uk post-punk', 'ukrainian indie', 'ukrainian pop', 'future garage',
        'indie soul', 'garage house', 'metropopolis', 'neo-synthpop', 'nyc pop', 'shimmer pop',
        'uk contemporary r&b', 'big room', 'dance pop', 'edm', 'pop', 'pop dance', 'piano rock',
        'uplifting trance', 'deep groove house', 'house', 'uk dance', 'detroit house', 'diva house',
        'acid house', 'detroit techno', 'minimal techno', 'techno', 'hi-nrg', 'new romantic',
        'new wave pop', 'sophisti-pop', 'synthpop', 'alternative dance', 'indie poptimism',
        'nu disco', 'art pop', 'chamber pop', 'indie rock', 'seattle indie', 'dark disco',
        'electro-pop francais', 'french indietronica', 'filter house', 'french indie pop',
        'new french touch', 'dutch house', 'electro house', 'progressive electro house',
        'progressive house', 'russian edm', 'sky room', 'breakbeat', 'psybreaks', 'uk pop',
        'eurodance', 'german techno', 'classic hardstyle', 'euphoric hardstyle', 'rawstyle',
        'classic russian pop', 'russian rock', 'russian synthpop', 'soviet synthpop', 'disco',
        'mellow gold', 'soft rock', 'soul', 'tribal house', 'progressive breaks', 'electronic rock',
        'sped up', 'alternative metal', 'hard rock', 'industrial', 'industrial metal',
        'industrial rock', 'nu metal', 'post-grunge', 'rock', 'christian metal', 'melodic metalcore',
        'metalcore', 'glam rock', 'melancholia', 'solo wave', 'steampunk', 'zolo', 'german dance',
        'tropical house', 'pop nacional', 'slap house', 'permanent wave'
    ];

    $result = [];
    foreach ($genres as $genre) {
        if (in_array(strtolower($genre), $highlightedGenres)) {
            $result[] = "<strong class='zhanr'>" . htmlspecialchars($genre) . "</strong>";
        } else {
            $result[] = htmlspecialchars($genre);
        }
    }
    return $result;
}

function getAllReleases($api, $limit = 20, $country = 'US') {
    $releases = [];
    $offset = 0;

    while (count($releases) < $limit) {
        try {
            $options = [
                'country' => $country,
                'limit' => 50,
                'offset' => $offset
            ];

            $albumsResponse = $api->getNewReleases($options);
            $albums = $albumsResponse->albums->items;

            foreach ($albums as $album) {
                if ($album->album_type === 'single' || $album->album_type === 'ep') {
                    $artistGenres = $api->getArtist($album->artists[0]->id)->genres;
                    $releases[] = [
                        'id' => $album->id,
                        'name' => $album->name,
                        'artist' => $album->artists[0]->name,
                        'release_date' => $album->release_date,
                        'image' => $album->images[0]->url ?? null,
                        'type' => $album->album_type,
                        'total_tracks' => $album->total_tracks,
                        'genres' => implode(', ', getParentGenres($artistGenres)), 
                        'uri' => $album->uri
                    ];

                    if (count($releases) >= $limit) {
                        break 2;
                    }
                }
            }

            $offset += 50;
            if ($offset >= $albumsResponse->albums->total) {
                break;
            }
        } catch (Exception $e) {
            error_log("Error fetching releases: " . $e->getMessage());
            break;
        }
    }

    usort($releases, function($a, $b) {
        return strcmp($b['release_date'], $a['release_date']);
    });

    return array_slice($releases, 0, $limit);
}

$releases = getAllReleases($api, $limit, $selectedCountry);

// Rest of your PHP code (HTML, CSS, and JavaScript) remains the same
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latest EP and Singles on Spotify</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles... */
    </style>
</head>
<body>
    <h2>Latest EP and Singles on Spotify</h2>

    <div class="controls">
        <form method="get" id="updateForm">
            <select name="country" id="country">
                <?php foreach ($spotifyCountries as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $code === $selectedCountry ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" id="limit" name="limit" value="<?php echo $limit; ?>" min="1" max="50">
            <button type="submit">Update</button>
        </form>
    </div>

    <div class="releases-container">
        <?php foreach ($releases as $release): ?>
            <div class="release-card" data-release-id="<?php echo htmlspecialchars($release['id']); ?>">
                <img src="<?php echo htmlspecialchars($release['image']); ?>" alt="Release cover" class="release-image">
                <div class="release-info">
                    <p class="release-name"><?php echo htmlspecialchars($release['name']); ?></p>
                    <p class="release-artist"><?php echo htmlspecialchars($release['artist']); ?></p>
                    <p class="release-details">
                        Type: <?php echo htmlspecialchars($release['type']); ?><br>
                        Tracks: <?php echo htmlspecialchars($release['total_tracks']); ?><br>
                        Release Date: <?php echo htmlspecialchars($release['release_date']); ?><br>
                        Genres: <?php echo $release['genres']; ?><br>
                    </p>
                    <button class="play-button" onclick="openSpotify('<?php echo $release['uri']; ?>')">Open in Spotify</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="trackModal" class="modal">
        <!-- Your existing modal HTML... -->
    </div>

    <script>
        // Your existing JavaScript...
    </script>
</body>
</html>