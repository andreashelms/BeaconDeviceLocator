# Beacon Device Locator

## Description

The project centers around providing indoor locations of Android devices using
Eddystone-Beacons.

### Links

* Live: https://beacon-device-locator.appspot.com

## Development

### Prerequisites
Make sure you have the following tools installed:

* Google Cloud SDK
* php
* MySQL
* Android SDK

### Installation

After cloning the repository, change to the sql-folder and run the bdl.sql on
your Google Cloud SQL Instance.

After that, change to the web-folder, open the app.yaml and change the environment
variables to those of your SQL-instance. Then deploy the App on your Google Cloud
PHP App Engine by entering:

gcloud app deploy

Now open the Android project located in the android-folder and deploy the Android-
App to the devices you want to locate.

### Develop

Run the following command to start the server on localhost:

cd web
dev_appserver.py .

### Deploy

cd web
gcloud app deploy

### Configuration

insert your beacon positions into location table of your SQL-instance.

for example:

INSERT INTO `location` (`name`, `tag`, `instance`, `lat`, `lng`, `x`, `y`) VALUES
('location1', 'be2', 'instance1', lat1, lng1, x1, y1),
('location2', 'be1', 'instance2', lat2, lng2, x2, y2),
('location3', 'be3', 'instance3', lat3, lng3, x3, y3),
('location4', 'be4', 'instance4', lat4, lng4, x4, y4);

x and y are the positions of your beacons in metric coordinates. make sure your
insert the right distances between beacons.
instance is the Eddystone-Instance-Id of your beacon. This is just for identification
issues and therefore optional to be set.

Configure your beacons to advertise the URL of the web interface of Beacon Device
Locator. Make sure you add the tag of your beacon you defined in 'location'-table
to the URL it is advertising, like so:

https://beacon-device-locator.appspot.com/?location=be1

Your Android-devices provided with the Beacon Device Locator-App should now be
able to discover the beacons around and automatically send their measured distances
to the PHP backend.

Optional

It may be necessary to define an offset for your device's measurements, because
there is a high variation between the measured distances of different bluetooth
modules.

You can adjust the offset of your device by updating the dataset of your device
in device table of your SQL-instance:

UPDATE `device` SET `offset` = '1' WHERE `name` = 'device name' AND
`macaddress` = '00:00:00:00:00:00';

The offset value is multiplied with the measured distances of your device.
