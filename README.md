# Beacon Device Locator

## Description

The project centers around providing indoor locations of Android devices using Eddystone-Beacons.

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
