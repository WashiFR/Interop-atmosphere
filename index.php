<?php

// Webetu
function query($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_PROXY, 'www-cache:3128');
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    return $response;
}

// Récupère l'adresse IP du client
$client_ip = $_SERVER['REMOTE_ADDR'] ?? "193.50.135.204";
// Cas du localhost
if ($client_ip == "127.0.0.1") {
    $client_ip = "193.50.135.204";
}

$ip_array = query("http://ip-api.com/json/{$client_ip}");
//var_dump($ip_array);

// Transforme le JSON en objet PHP et récupère les coordonnées
$ip_json = json_decode($ip_array);
//var_dump($ip_json);

// Si l'API a échoué
if ($ip_json->status == "fail") {
    $ip_array = query("http://api-adresse.data.gouv.fr/search/?q=IUT%20Nancy%20Charlemagne");
    $ip_json = json_decode($ip_array);
//    var_dump($ip_json);
    $lat = $ip_json->features[0]->geometry->coordinates[1];
    $lont = $ip_json->features[0]->geometry->coordinates[0];
} else {
    $lat = $ip_json->lat;
    $lont = $ip_json->lon;
}

//echo "Latitude : $lat, Longitude : $lont";

// Récupère les données routiers
$trafic = query("https://carto.g-ny.org/data/cifs/cifs_waze_v2.json");
$traficData = json_decode($trafic, true);
$traficJson = json_encode($traficData['incidents']);
//var_dump($traficJson);

// Récupère les données de l'air
$air = query("https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D'Nancy'&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=");
$airJson = json_decode($air, true);
//var_dump($airJson);

$latestFeature = null;
$today = (new DateTime())->format('Y-m-d');
foreach ($airJson["features"] as $feature) {
    $timestamp = $feature["attributes"]["date_ech"] / 1000;
    $featureDate = (new DateTime("@$timestamp"))->format('Y-m-d');
    if ($feature["attributes"]["lib_zone"] == 'Nancy' && $featureDate == $today) {
        $latestFeature = $feature;
        break;
    }
}
$pollution = $latestFeature["attributes"]["lib_qual"];

// Récupère les données météo
$array = query("https://www.infoclimat.fr/public-api/gfs/xml?_ll={$lat},{$lont}&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D&_c=19f3aa7d766b6ba91191c8be71dd1ab2");
//var_dump($array);

//$xml = simplexml_load_string($array[0]);
//var_dump($xml);

$xml = new DOMDocument();
$xml->loadXML($array);

$xsl_style = new DOMDocument();
$xsl_style->load("style.xsl");

$proc = new XSLTProcessor();
$proc->importStylesheet($xsl_style);
$html = $proc->transformToXML($xml);    // Conversion en HTML

// ### Page web ###
echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Météo & circulation</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
    crossorigin=""/>
</head>
<body>
    $html
    
    <h2>Qualité de l'air à Nancy : $pollution</h2>
    
    <div id="map" style="height: 500px"></div>

    <div class="links">
        <h3>Liens utiles</h3>
        <ul>
            <li>Github : <a target="_blank" href=""></a></li>
            <li>API météo : 
                <a target="_blank" 
                href="https://www.infoclimat.fr/public-api/gfs/xml?_ll={$lat},{$lont}&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D&_c=19f3aa7d766b6ba91191c8be71dd1ab2">
                Infoclimat</a>
            </li>
            <li>API ip : 
                <a target="_blank" 
                href="http://ip-api.com/json/{$client_ip}">
                http://ip-api.com/json/{$client_ip}</a>
            </li>
            <li>API trafic : 
                <a target="_blank" 
                href="https://carto.g-ny.org/data/cifs/cifs_waze_v2.json">
                https://carto.g-ny.org/data/cifs/cifs_waze_v2.json</a>
            </li>
            <li>API qualité de l'air : 
                <a target="_blank" 
                href="https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D'Nancy'&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=">
                https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0
                </a>
            </li>
        </ul>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>
    <script>
        var map = L.map('map').setView([$lat, $lont], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);
        
        var incidents = $traficJson;
        
        incidents.forEach(incident => {
            var coord = incident.location.polyline.split(' ');
            var lat = parseFloat(coord[0]);
            var lon = parseFloat(coord[1]);
            
            L.marker([lat, lon]).addTo(map)
                .bindPopup(incident.short_description)
                .openPopup();
        });
        
        L.marker([$lat, $lont]).addTo(map)
            .bindPopup('Vous êtes ici')
            .openPopup();
    </script>
</body>
</html>
HTML;

?>