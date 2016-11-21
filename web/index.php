<?php

$GLOBALS['mysqli'] = new mysqli(
  null,
  getenv('MYSQL_USER'),
  getenv('MYSQL_PASSWORD'),
  getenv('MYSQL_DATABASE'),
  null,
  getenv('MYSQL_DSN'));
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: (
      " . $mysqli->connect_errno . "
    ) " . $mysqli->connect_error;
}

// $_GET["name"] = phone name
// $_GET["mac"] = mac address
// $_GET["location"] = beacon tag
// $_GET["distance"] = device distance
if($_GET["name"] != "" || $_GET["mac"] != ""){
    if(!checkIfDeviceExists($_GET['name'], $_GET['mac'])){
      insertDevice($_GET["name"], $_GET["mac"], $_GET["location"], $_GET["distance"]);
    }else{
      updateDevice($_GET["name"], $_GET["mac"], $_GET["location"], $_GET["distance"]);
    }
}else{
    echo "Beacon Device Locator";
    showDevicesOnMap();
    showBeaconsOnMap();
}

//checks if device exists in db
function checkIfDeviceExists($name, $macaddress){
  $devices_query = $GLOBALS['mysqli']->query(
    "select * from device where name = '".$name."'
    AND macaddress = '".$macaddress."'"
  );
  if($devices_query->num_rows == 0){
    return false;
  }else{
    return true;
  }
}

//inserts an new device in db
function insertDevice($name, $macaddress, $tag, $distance){
  if($query_exec = $GLOBALS['mysqli']->query(
    "INSERT INTO device (name, macaddress)
    VALUES ('".$name."',
            '".$_GET["mac"]."')"
    )){
      $query_exec = $GLOBALS['mysqli']->query(
      "INSERT INTO distance (location, distance, name, macaddress)
      VALUES ('".$tag."',
             '".$distance."',
             '".$name."',
              '".$macaddress."')"
      );
      echo 'Device added to database.';
  } else {
      echo 'Device could not be added.';
  }
}

//updates measurements of known device in db
function updateDevice($name, $macaddress, $tag, $distance){
  $time = new DateTime();
  $time->setTimezone(new DateTimeZone('Europe/London'));
  if($query_exec = $GLOBALS['mysqli']->query(
    " UPDATE device SET timestamp = '".$time->format('Y-m-d H:i:s')."' WHERE name = '".$name."'
    AND macaddress = '".$_GET['mac']."'"
  ))
  {
      if($query_exec = $GLOBALS['mysqli']->query(
      "INSERT INTO distance (name, macaddress, tag, distance)
      VALUES ('".$name."',
             '".$macaddress."',
             '".$tag."',
              '".$distance."')"
      )){
        echo 'Device updated in database.';
      }else{
        echo 'Device could not be updated.';
      }
  } else {
    echo 'Device could not be updated.';
  }
}

//shows beacons on map
function showBeaconsOnMap(){
  $numBeacons = 0;
  $beacon_query = $GLOBALS['mysqli']->query(
    "select * from location"
  );
  while ($beacon_row = $beacon_query->fetch_assoc()){
    //beacon on default (0,0) position
    if($beacon_row['x'] == 0 && $beacon_row['y'] == 0){
      echo "<input id=be_lat_default type=hidden value=".$beacon_row['lat']."></input>";
      echo "<input id=be_lng_default type=hidden value=".$beacon_row['lng']."></input>";
    }
    echo "<input id=be_lat".$beacon_row['id']." type=hidden value=".$beacon_row['lat']."></input>";
    echo "<input id=be_lng".$beacon_row['id']." type=hidden value=".$beacon_row['lng']."></input>";
    echo "<input id=be_name".$beacon_row['id']." type=hidden value=".$beacon_row['name']."></input>";
    $numBeacons = $beacon_row['id'];
  }
  echo "<input id=beacon_count type=hidden value=".$numBeacons."></input>";
}

//shows devices on the map
function showDevicesOnMap(){
  $measurementCount = 0;
  $intermediateCount = 0;

  foreach (getDevicesFromDB() as $key => $device) {
    echo '<pre>';
    echo $device['name'].", ".
         $device['macaddress'].", ";

    if($_POST["from"]){
      $time = new DateTime($_POST["from"]);
      $from = $time->format('Y-m-d H:i:s');
    }
    if($_POST["til"]){
      $time = new DateTime($_POST["til"]);
      $til = $time->format('Y-m-d H:i:s');
    }
    $distances = getDistancesFromDB(
      $device['name'],
      $device['macaddress'],
      $from,
      $til);

    //show latest Measurements on top of page
    $locations = array();
    foreach ($distances as $key => $distance) {
      if(!in_array($distance['name'],$locations) && sizeof($locations) < 4){
        $locations[] = $distance['name'];
        echo ($distance['name'] != "" ? $distance['name'] : "???").": ".
             $distance['distance'] * $device['offset']."m, ";
      }
    }
    echo $distances[0]['timestamp'];

    echo '</pre>';

    if(!$_POST["device"] ||
        $_POST["device"] == all ||
        $_POST["device"] == $device['name']){

      echo '<pre>';

      if($_POST["measurement"] == "latest"){
          $beacons = getLatestMeasurements($distances);
      }else if($_POST["measurement"] == "average"){
          $beacons = getBeaconAverageDistances($distances);
      }else if($_POST["measurement"] == "frequent"){
          $beacons = getMostFrequentBeaconDistances($distances);
      }
      $measurements = array();
      $measurements[] = $beacons;
      if($_POST["measurement"] == "all"){
          $measurements = uniteMeasurementsOfSameTime($distances);
      }

      foreach ($measurements as $measureKey => $beacons) {
        $intermediatePoints = array();
        if(sizeof($beacons) >= 4){
          $measurementCount++;
          $beaconCombo = array($beacons);
          if($_POST["trilateration"] == "multiple"){
              $beaconCombo = getCombosOfLength($beacons, 3);
          }else if($_POST["trilateration"] == "nearest"){
              $beaconCombo = getNearestBeacons($beacons);
          }
          $coordinate = (object) ['x' => 0, 'y' => 0];
          foreach ($beaconCombo as $key => $beacon) {
            $tempCoordinate = calculateDevicePosition(
              $beacon[0]->coordinate,
              $beacon[1]->coordinate,
              $beacon[2]->coordinate,
              $beacon[0]->distance * $device['offset'],
              $beacon[1]->distance * $device['offset'],
              $beacon[2]->distance * $device['offset']
            );
            if($tempCoordinate->x != 0 || $tempCoordinate->y != 0){
              $coordinate->x = $coordinate->x + $tempCoordinate->x;
              $coordinate->y = $coordinate->y + $tempCoordinate->y;
              $intermediatePoints[] = (object) [
                                        'x' => $tempCoordinate->x,
                                        'y' => $tempCoordinate->y
                                      ];
            }
          }
          $coordinate->x = $coordinate->x / sizeof($beaconCombo);
          $coordinate->y = $coordinate->y / sizeof($beaconCombo);

          if($_POST["trilateration"] == "multiple"){
            foreach ($intermediatePoints as $index => $point) {
              $intermediateCount++;
              echo "<input id=inter_point_x".$intermediateCount." type=hidden value=".$point->x."></input>";
              echo "<input id=inter_point_y".$intermediateCount." type=hidden value=".$point->y."></input>";
            }
          }

          echo "<input id=x".$measurementCount." type=hidden value=".$coordinate->x."></input>";
          echo "<input id=y".$measurementCount." type=hidden value=".$coordinate->y."></input>";
          echo "<input id=device".$measurementCount." type=hidden value=".$device['name']."></input>";
        }
      }

      echo '</pre>';
    }
  }
  echo "<input id=count type=hidden value=".$measurementCount."></input>";
  echo "<input id=inter_point_count type=hidden value=".$intermediateCount."></input>";

  showFilters($measurementCount);
}

//retrieves all known devices from db
function getDevicesFromDB(){
  $devices = array();
  $deviceQuery = $GLOBALS['mysqli']->query("select * from device order by timestamp desc");
  while ($deviceRow = $deviceQuery->fetch_assoc()) {
    $devices[] = $deviceRow;
  }
  return $devices;
}

//retrieves all distances for known devices from db and joins the beacon locations
function getDistancesFromDB($deviceName, $deviceMacAddress, $from, $til){
  $distances = array();
  $distanceQuery = $GLOBALS['mysqli']->query(
    "select * from distance
    left join location on
    distance.tag = location.tag
    where distance.name = '".$deviceName."' AND
    macaddress = '".$deviceMacAddress."' AND
    NOT distance = 0".
    (($from) ? " AND timestamp >= '".$from."'" : " ").
    (($til) ? " AND timestamp <= '".$til."'" : " ").
    "order by timestamp desc"
  );
  //Messung Galaxy S6 getDistace//timestamp > '2016-11-10 08:58:31' AND timestamp < '2016-11-10 10:36:31'
  //Messung Alcatel overnigh// timestamp < '2016-11-09 15:58:31'
  //Messung Galaxy S6 with tx and RSSI//timestamp > '2016-11-10 10:36:31' AND  timestamp < '2016-11-10 12:10:00'
  //LiveTest Samsung//timestamp > '2016-11-10 12:25:00' AND timestamp < '2016-11-10 12:55:00'
  //Messung Alcatel Day //timestamp > '2016-11-10 12:55:00' AND timestamp < '2016-11-10 15:20:00'
  //LiveTest Alcatel//
  //Messung Alcatel overnight 2 // timestamp > '2016-11-10 18:03' AND timestamp < '2016-11-10 09:45'
  while ($distanceRow = $distanceQuery->fetch_assoc()){
    $distances[] = $distanceRow;
  }
  return $distances;
}

//returns the most frequent measured distances to all beacons for one device
function getMostFrequentBeaconDistances($distances){
  $sortedDistances = (object) [];
  $sortedLocations = (object) [];
  foreach ($distances as $key => $distance) {
    $locationDistances = $sortedDistances->$distance['tag'];
    if(!$locationDistances){
      $locationDistances = array();
      $sortedLocations->$distance['tag'] = array($distance['x'],$distance['y']);
    }
    $locationDistances[] = $distance['distance'];
    $sortedDistances->$distance['tag'] = $locationDistances;
  }
  $beacons = array();
  foreach ($sortedDistances as $location => $locationDistances) {
    $coordinates = $sortedLocations->$location;
    $c = array_count_values($locationDistances);
    $beacons[] = (object) ['coordinate' => (object) [
                              'x' => $coordinates[0],
                              'y' => $coordinates[1]
                            ],
                            'distance' => array_search(max($c), $c)];
  }
  return $beacons;
}

//returns the average of all measured distances to all beacons for one device
function getBeaconAverageDistances($distances){
  $sortedDistances = (object) [];
  $sortedLocations = (object) [];
  foreach ($distances as $key => $distance) {
    $locationDistances = $sortedDistances->$distance['tag'];
    if(!$locationDistances){
      $locationDistances = array();
      $sortedLocations->$distance['tag'] = array($distance['x'],$distance['y']);
    }
    $locationDistances[] = $distance['distance'];
    $sortedDistances->$distance['tag'] = $locationDistances;
  }
  $beacons = array();
  foreach ($sortedDistances as $location => $locationDistances) {
    $coordinates = $sortedLocations->$location;
    $averageDistance = 0;
    foreach ($locationDistances as $key => $distance) {
      $averageDistance = $averageDistance + $distance;
    }
    $averageDistance = $averageDistance / sizeof($locationDistances);
    $beacons[] = (object) ['coordinate' => (object) [
                              'x' => $coordinates[0],
                              'y' => $coordinates[1]
                            ],
                            'distance' => $averageDistance];
  }
  return $beacons;
}

//returns the latest measured distances to all beacons for one device
function getLatestMeasurements($distances){
  $beacons = array();
  foreach ($distances as $key => $distance) {
    if(checkIfBeaconNotExistsInArray($beacons, $distance)){
      $beacons[] = (object) ['coordinate' => (object) [
                                'x' => $distance['x'],
                                'y' => $distance['y']
                              ],
                              'distance' => $distance['distance']];
    }
    if(sizeof($beacons) == 4){
      break;
    }
  }
  //var_dump($beacons);
  return $beacons;
}


//returns all measured distances to all beacons for all points in time for one device
function uniteMeasurementsOfSameTime($distances){
  $measurements = array();
  $beacons = array();
  foreach ($distances as $key => $distance) {
    $date = date('F j, Y, g:i a', strtotime($distance['timestamp']));
    if($date != $dateBefore){
      if(sizeof($beacons) != 0){
          $measurements[] = $beacons;
      }
      $beacons = array();
    }
    if(checkIfBeaconNotExistsInArray($beacons, $distance)){
      $beacons[] = (object) ['coordinate' => (object) [
                                'x' => $distance['x'],
                                'y' => $distance['y']
                              ],
                              'distance' => $distance['distance'],
                              'timestamp' => $distance['timestamp']];
    }
    $dateBefore = $date;
  }
  return $measurements;
}

//helper function for uniteMeasurementsOfSameTime
function checkIfBeaconNotExistsInArray($beacons, $distance){
  foreach ($beacons as $key => $beacon) {
    if(
      $beacon->coordinate->x == $distance['x'] &&
      $beacon->coordinate->y == $distance['y']
    ){
      return false;
    }
  }
  return true;
}

//calculats the device position via trilateration
//a = (x,y) of a
//b = (x,y) of b
//c = (x,y) of c
//ra = distance to a
//rb = distance to b
//rb = distance to c
function calculateDevicePosition($a, $b, $c, $ra, $rb, $rc)  {
  $xa = $a->x;
  $ya = $a->y;
  $xb = $b->x;
  $yb = $b->y;
  $xc = $c->x;
  $yc = $c->y;

  $W = $dA*$dA - $dB*$dB - $latA*$latA - $lngA*$lngA + $latB*$latB + $lngB*$lngB;
  $Z = $dB*$dB - $dC*$dC - $latB*$latB - $lngB*$lngB + $latC*$latC + $lngC*$lngC;

  $S = (pow($xc, 2) - pow($xb, 2) + pow($yc, 2) - pow($yb, 2) + pow($rb, 2) - pow($rc, 2)) / 2.0;
  $T = (pow($xa, 2) - pow($xb, 2) + pow($ya, 2) - pow($yb, 2) + pow($rb, 2) - pow($ra, 2)) / 2.0;

  $y1 = (($T * ($xb - $xc)) - ($S * ($xb - $xa)));
  $y2 = ((($ya - $yb) * ($xb - $xc)) - (($yc - $yb) * ($xb - $xa)));
  if($y2 != 0){
    $y = $y1 / $y2;
  }
  $x1 = (($y * ($ya - $yb)) - $T);
  $x2 = ($xb - $xa);
  if($x2 != 0){
    $x = $x1 / $x2;
  }

  return (object) ['x' => $x, 'y' => $y];
}

//return all combinations of elements in array with a defined length
function getCombosOfLength($arrInput, $intLength) {
    $arrOutput = array();
    if ($intLength == 1) {
        for ($i = 0; $i < count($arrInput); $i++) $arrOutput[] = array($arrInput[$i]);
        return $arrOutput;
    }
    $arrShift = $arrInput;
    while (count($arrShift)) {
        $x = array_shift($arrShift);
        $arrReturn = getCombosOfLength($arrShift, $intLength - 1);
        for ($i = 0; $i < count($arrReturn); $i++) {
            array_unshift($arrReturn[$i], $x);
            $arrOutput[] = $arrReturn[$i];
        }
    }
    return $arrOutput;
}

function cmp($a, $b){
  if ($a->distance == $b->distance) {
      return 0;
  }
  return ($a->distance < $b->distance) ? -1 : 1;
}

function getNearestBeacons($beacons){
  usort($beacons, "cmp");
  return array(array_slice($beacons, 0, 3));
}

function showFilters($measurementCount){
  echo
  '<form action="/index.php" method="post">
    <div>
      <input type="radio" id="latest" name="measurement" value="latest"
        '.(!$_POST["measurement"] || $_POST["measurement"] == "latest" ? "checked" : " ").'>
      </input>
      <label for="latest">Latest Measuement</label>
      <input type="radio" id="average" name="measurement" value="average"
        '.($_POST["measurement"] && $_POST["measurement"] == "average" ? "checked" : " ").'>
      </input>
      <label for="average">Average Measurement</label>
      <input type="radio" id="frequent" name="measurement" value="frequent"
        '.($_POST["measurement"] && $_POST["measurement"] == "frequent" ? "checked" : " ").'>
      </input>
      <label for="all">Most Frequent</label>
      <input type="radio" id="all" name="measurement" value="all"
        '.($_POST["measurement"] && $_POST["measurement"] == "all" ? "checked" : " ").'>
      </input>
      <label for="all">All Measurements</label>
      <input id="slide" type="range" min="1" max="'.$measurementCount.'"
        step="1" value="'.$measurementCount.'" onchange="updateSlider(this.value)">
      </input>
      <input id="sliderAmount" size="4" value="'.$measurementCount.'"></input>
      <select name="device">
            <option '
              .($_POST["device"] && $_POST["device"] == "all" ? "selected" : " ").'>
              all
            </option>';
      foreach (getDevicesFromDB() as $key => $device) {
        echo '<option '
                .($_POST["device"] && $_POST["device"] == $device["name"] ? "selected" : " ").'>'
                .$device['name'].
             '</option>';
      }
  echo '</select>
      </br>
      <input type="radio" id="multiple" name="trilateration" value="multiple" '
        .(!$_POST["trilateration"] || $_POST["trilateration"] == "multiple" ? "checked" : " ").'>
      </input>
      <label for="average">Multiple Trilateration</label>
      <input type="radio" id="nearest" name="trilateration" value="nearest" '
        .($_POST["trilateration"] && $_POST["trilateration"] == "nearest" ? "checked" : " ").'>
      <label for="average">Nearest Trilateration</label>
    </div>
    </br>
    <div>
      <input type="datetime-local" name="from" value="'.$_POST["from"].'""></input>
      <input type="datetime-local" name="til" value="'.$_POST["til"].'""></input>
      <button type="submit" name="action">speichern</button>
    </div>
  </form>';
}

?>
<!DOCTYPE html>
<html>
  <head>
    <style>
       html {height: 100%;}
       body {height: 100%;}
       #map {
        height: 800;
        width: 100%;
       }
    </style>
  </head>
  <body>
    <div id="map"></div>
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB-CWVpgnWinuMURZniPsy9WAaiw1r_h-g&callback=initMap&libraries=geometry">
    </script>
    <script>
      var markers = [];
      var intermediateMarkers = [];
      var averageMarker;
      var map;
      const defaultLat = parseFloat(document.getElementById("be_lat_default").value);
      const defaultLng = parseFloat(document.getElementById("be_lng_default").value);

      updateSlider = function(slideAmount){
        var sliderValue = document.getElementById("slide").value;
        document.getElementById("sliderAmount").value = slideAmount;

        clearMarkers();
        setMap(sliderValue, map);

      }

      Number.prototype.toRad = function() {
        return this * Math.PI / 180;
      }

      Number.prototype.toDeg = function() {
        return this * 180 / Math.PI;
      }

      destinationPoint = function(latLng, brng, dist) {
         dist = dist / 1000 / 6371;
         brng = brng.toRad();

         var lat1 = latLng.lat().toRad(), lon1 = latLng.lng().toRad();

         var lat2 = Math.asin(Math.sin(lat1) * Math.cos(dist) +
                              Math.cos(lat1) * Math.sin(dist) * Math.cos(brng));

         var lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(dist) *
                                      Math.cos(lat1),
                                      Math.cos(dist) - Math.sin(lat1) *
                                      Math.sin(lat2));

         if (isNaN(lat2) || isNaN(lon2)) return null;

         return new google.maps.LatLng(lat2.toDeg(), lon2.toDeg());
      }

      function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
          zoom: 21,
          center: {lat: defaultLat, lng: defaultLng}
        });

        const markerCount = parseInt(document.getElementById("count").value);
        addMarkers(markerCount, map);

        const intermediateMarkerCount = parseInt(document.getElementById("inter_point_count").value);
        addMarkersForIntermediatePoints(intermediateMarkerCount, map);

        setMapOnAll(map);

        for(var i = 1; i <= document.getElementById("beacon_count").value; i++){
          var marker = new google.maps.Marker({
            icon: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            position: {
              lat: parseFloat(document.getElementById("be_lat" + i).value),
              lng: parseFloat(document.getElementById("be_lng" + i).value)},
            label:document.getElementById("be_name" + i).value,
            map: map
          });
        }
      }

      clearMarkers = function() {
        setMapOnAll(null);
      }

      setMapOnAll = function(map) {
        for (var i = 0; i < markers.length; i++) {
          markers[i].setMap(map);
        }
        for (var i = 0; i < intermediateMarkers.length; i++) {
          intermediateMarkers[i].setMap(map);
        }
        calculateAverageLatLng(markers.length);
      }

      setMap = function(number, map) {
        for (var i = 0; i < number; i++) {
          markers[i].setMap(map);
        }
        for (var i = 0; i < number * 4; i++) {
          intermediateMarkers[i].setMap(map);
        }
        calculateAverageLatLng(number);
      }

      addMarkers = function(number, map){
        for(var i = 1; i <= number; i++){
          var lat = destinationPoint(
            new google.maps.LatLng(defaultLat, defaultLng),
            60,
            document.getElementById("x" + i).value
          ).lat();
          var lng = destinationPoint(
            new google.maps.LatLng(defaultLat, defaultLng),
            60, document.getElementById("x" + i).value
          ).lng();
          var latLng = destinationPoint(
            new google.maps.LatLng(lat, lng),
            330,
            document.getElementById("y" + i).value
          );
          var marker = new google.maps.Marker({
            position: {lat: latLng.lat(),
                       lng: latLng.lng()},
            label:document.getElementById("device" + i).value,
            map: map
          });
          markers.push(marker);
        }
      }

      addMarkersForIntermediatePoints = function(number, map){
        for(var i = 1; i <= number; i++){
          var lat = destinationPoint(
            new google.maps.LatLng(defaultLat, defaultLng),
            60, document.getElementById("inter_point_x" + i).value
          ).lat();
          var lng = destinationPoint(
            new google.maps.LatLng(defaultLat, defaultLng),
            60,
            document.getElementById("inter_point_x" + i).value
          ).lng();
          var latLng = destinationPoint(
            new google.maps.LatLng(lat, lng),
            330,
            document.getElementById("inter_point_y" + i).value
          );
          var marker = new google.maps.Marker({
            icon: 'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png',
            position: {lat: latLng.lat(),
                       lng: latLng.lng()},
            map: map
          });
          intermediateMarkers.push(marker);
        }
      }

      calculateAverageLatLng = function(number){
        if(averageMarker){
          averageMarker.setMap(null);
        }
        if(number != 1){
          var device = document.getElementById("device1").value;
          var goOn = true;
          var x = 0;
          var y = 0;
          for (var i = 1; i <= number; i++) {
            x = x + parseFloat(document.getElementById("x" + i).value);
            y = y + parseFloat(document.getElementById("y" + i).value);
            if(document.getElementById("device" + i).value != device){
              goOn = false;
              break;
            }
            device = document.getElementById("device" + i).value;
          }
          if(goOn){
            var averageLat = destinationPoint(
              new google.maps.LatLng(defaultLat, defaultLng),
              60,
              x / number
            ).lat();
            var averageLng = destinationPoint(
              new google.maps.LatLng(defaultLat, defaultLng),
              60,
              x / number
            ).lng();
            var averageLatLng = destinationPoint(
              new google.maps.LatLng(averageLat, averageLng),
              330,
              y / number
            );
            averageMarker = new google.maps.Marker({
              icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
              position: {lat: averageLatLng.lat(),
                         lng: averageLatLng.lng()},
              label:device,
              map: map
            });
          }
        }
      }
    </script>
  </body>
</html>
