<?php
require_once 'vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

$ants = "https://passeport.ants.gouv.fr";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $ants . "/securimage/show");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, "test.cookies");
curl_setopt($ch, CURLOPT_COOKIEFILE, "test.cookies");
curl_setopt($ch, CURLOPT_VERBOSE, false);

$image = curl_exec($ch);
$tmpfile = tmpfile();
fwrite($tmpfile, $image);
$filename = stream_get_meta_data($tmpfile)['uri'];
$magick = new Imagick($filename);
$magick->floodFillPaintImage("white", 0, "#8C8C8C", 0, 0, true);
$out = tmpfile();
fwrite($out, $magick);
$tessFile = stream_get_meta_data($out)['uri'];
$text = (new TesseractOCR($tessFile))
    ->whitelist(range('a', 'z'), range(0, 9))
    ->psm(8)
    ->run();

$answer = preg_replace("/[^a-zA-Z0-9]/", "", $text);

curl_setopt($ch, CURLOPT_URL, $ants . "/suivi_passeport/ask?location_id=2638");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "suivipasseport[numpasseport]=" . getenv("NUMERO_DEMANDE") . "&captcha_code=" . $answer);

$html = curl_exec($ch);
curl_close($ch);

if(strpos($html, "Le captcha n'est pas valide")) {
    echo "failed\n";
    return 0;
}

$intersting = explode("Retrouvez <span>nous sur</span>", explode("Suivi du titre", $html)[1])[0];
$active = explode('<div class="path-item-label">', explode("path-item-active", $intersting)[1])[1];
$active = trim(html_entity_decode(explode("</div>", $active)[0], ENT_QUOTES)) . "\n";
echo $active;

$frontend = fopen("index.html", "w");
fwrite($frontend, "<!doctype html><html><head><title>ANTS Check page</title></head><body><p>" . $active . "</p><p>Last update : " . date("d/m/Y H:i:s") ."</body></html>");
fclose($frontend);
