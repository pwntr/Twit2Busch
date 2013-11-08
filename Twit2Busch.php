<?php

/**
 * Twit2Busch
 * Hält den StudiVZ- und Twitter-Status synchron
 *
 *
 * Copyright 2009 Peter Winter
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * Please follow @pwntr on Twitter!
 * @author Peter Winter
 * @version 0.03
 *
 */

/* ****************** MAGIC DOWN BELOW ****************** */

// Konfiguration einbinden
include_once("config.php");

// Twitter API-Wrapper einbinden
include_once("class.twitter.php");

function getTwitterObject() {

	$twitter = new twitter;
	$twitter->username = $GLOBALS["loginTwitter"];
	$twitter->password = $GLOBALS["passwordTwitter"];

	return $twitter;

}

function whatsNew() {
	
	$whatsNew = "nix";
	
	if (file_exists('whatsNew.temp')) {
		
		$filePointer = fopen("whatsNew.temp", "r");
		$whatsNew = fread($filePointer, filesize("whatsNew.temp"));
		fclose($filePointer);
	
	}
	
	return $whatsNew;
	
}

function writeWhatsNew($name) {

	$filePointer = fopen("whatsNew.temp", "w");
	fwrite($filePointer, $name);
	fclose($filePointer);

}

function writeHashFile($name, $text) {
	
	// Text hashen und in Datei abspeichern
	$hash = md5($text);
	
	$filePointer = fopen($name, "w");
	fwrite($filePointer, $hash);
	fclose($filePointer);

}

function readHashFile($name) {
	
	if (file_exists($name)) {
		// Texthashes aus .temp-Dateien auslesen
		$hash = $name;
		$filePointer = fopen($hash, "r");
		$hash = fread($filePointer, filesize($hash));
		fclose($filePointer);
	} else {
		$hash = null;
	}
	
	return $hash;
	
}

function updateStatusStudiVZ($status) {

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Neue Session generieren
	
	$url = "http://m.studivz.net/op/studivz/de/mcat/login/"; // Für andere VZ’s bitte das Studivz durch meinvz oder schuelervz ersetzen
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en)");
	$buffer = curl_exec($ch);
	$needle = "/op/studivz/de/mcat/login/;jsessionid=";
	$session = substr($buffer,strpos($buffer,$needle)+strlen($needle),32);
	
	// Einloggen
	
	$url = "http://m.studivz.net/op/studivz/de/mcat/login/;jsessionid=" . $session; // Für andere VZ’s bitte das Studivz durch meinvz oder schuelervz ersetzen
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "username=" . $GLOBALS["loginStudiVZ"] . "&password=" . $GLOBALS["passwordStudiVZ"]);
	$buffer = curl_exec($ch);
	
	// Aktualisierung durchführen
	
	$url = "http://m.studivz.net/op/studivz/de/mcat/status/senden/;jsessionid=" . $session; // Für andere VZ’s bitte das Studivz durch meinvz oder schuelervz ersetzen
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "text=" . $status);
	$buffer = curl_exec($ch);
	
	echo "Status im StudiVZ erfolgreich aktualisiert!\n";

}

function isNew($nameOfFile, $status) {
	
	$hash = readHashFile($nameOfFile);
	$newHash = md5($status);
	
	if($hash != $newHash) {
		writeHashFile($nameOfFile, $status);
		writeWhatsNew($nameOfFile);
		return true;
	} else {
		return false;	
	}
	
}

function readStatusTwitter($twitter) {

	$data = $twitter->userTimeline();
	$status = $data[0]->text;

	$status = html_entity_decode($status);

	isNew("twitterStatus.temp", $status);

	return $status;

}

function updateStatusTwitter($twitter, $status) {

	$twitter->update($status);

	isNew("twitterStatus.temp", $status);
	
	echo "Status bei Twitter erfolgreich aktualisiert!\n";

}

function hasToBeFiltered($tweet) {
	
	// Soll überhaupt gefiltert werden?
	if ($GLOBALS["excludeReplies"]) {
	
		if(($tweet[0] == "@") || (($tweet[0] == "R") && ($tweet[1] == "T")) || ($tweet == "Halte Deine Freunde auf dem Laufenden.")) {
			return true;
		} else {
			return false;
		}
		
	} else {
		return false;
	}
	
}

function checkForHashtag($tweet) {
	
	$hashTag = $GLOBALS["onlyTweetWithTag"];
	
	if ((strpos($tweet, $hashTag) !== false) || ($hashTag == "")) {
		return true;
	} else {
		return false;
	}
	
}

function readStatusStudiVZ() {

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Neue Session generieren
	
	$url = "http://m.studivz.net/op/studivz/de/mcat/login/"; // Fuer andere VZ’s bitte das Studivz durch meinvz oder schuelervz ersetzen
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en)");
	$buffer = curl_exec($ch);
	$needle = "/op/studivz/de/mcat/login/;jsessionid=";
	$session = substr($buffer,strpos($buffer,$needle)+strlen($needle),32);
	
	// Einloggen
	
	$url = "http://m.studivz.net/op/studivz/de/mcat/login/;jsessionid=" . $session; // Fuer andere VZ’s bitte das Studivz durch meinvz oder schuelervz ersetzen
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "username=" . $GLOBALS["loginStudiVZ"] . "&password=" . $GLOBALS["passwordStudiVZ"]);
	$buffer = curl_exec($ch);


	// Hole die gesamte Seite
	curl_setopt($ch, CURLOPT_URL, "http://m.studivz.net/op/studivz/de/mcat/start/;jsessionid=" . $session);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$buffer = curl_exec($ch);

	// Vom StudiVZ eingefügte <br/> Zeilenumbrüche aus der Statusnachricht entfernen und durch Leerzeichen ersetzen
	$data = str_replace('<br/>', ' ', $buffer);
	
	// Alle Tags bis auf die divs entfernen
	$data = strip_tags($data, '<div>');

	// Dokument zur DOM-Manipulation erstellen
	$doc = new DOMDocument('1.0', 'UTF-8');
	$doc->loadHTML($data);
	$elements = $doc->getElementsByTagName('div');
	
	// Auf das div mit der Statusnachricht zugreifen
	$status = $elements->item(8)->nodeValue;
	
	// Whitespaces entfernen und Encoding richten
	$status = utf8_decode(stripslashes(trim($status)));
	
	isNew("studiVZStatus.temp", $status);
	
	return $status;

}

function statusIsEqual() {
	
	// Texthashes aus .temp-Dateien auslesen
	$studiVZHash = readHashFile("studiVZStatus.temp");
	$twitterHash = readHashFile("twitterStatus.temp");

	if ($twitterHash == $studiVZHash) {
		return true;
	} else {
		return false;
	}
	
}


// Eigentliche Logik
$twitter = getTwitterObject();

$studivzMessage = readStatusStudiVZ();
$twitterMessage = readStatusTwitter($twitter);


if(statusIsEqual()) {

	echo "Sind gleich, kein Update steht an!\n";

} else {

	if((whatsNew() == "studiVZStatus.temp") && (!hasToBeFiltered($studivzMessage))) {
		
		updateStatusTwitter($twitter, $studivzMessage);
		
	} else {

		if((whatsNew() == "twitterStatus.temp") && (!hasToBeFiltered($twitterMessage)) && (checkForHashtag($twitterMessage))) {
			
			updateStatusStudiVZ($twitterMessage);
		
		} else {
		
			echo "Status ist @-Reply, Retweet oder ihm fehlt das Hashtag. Status wird ignoriert!";
	
		}
	}

}

?>