package devicelocator.ubilabs.net.devicelocator;

import android.app.Application;
import android.bluetooth.BluetoothAdapter;
import android.content.Intent;
import android.os.Build;
import android.os.RemoteException;
import android.text.TextUtils;
import android.util.Log;
import android.widget.TextView;

import org.altbeacon.beacon.Beacon;
import org.altbeacon.beacon.BeaconConsumer;
import org.altbeacon.beacon.BeaconManager;
import org.altbeacon.beacon.BeaconParser;
import org.altbeacon.beacon.Identifier;
import org.altbeacon.beacon.RangeNotifier;
import org.altbeacon.beacon.Region;
import org.altbeacon.beacon.powersave.BackgroundPowerSaver;
import org.altbeacon.beacon.startup.BootstrapNotifier;
import org.altbeacon.beacon.startup.RegionBootstrap;
import org.altbeacon.beacon.utils.UrlBeaconUrlCompressor;

import java.io.IOException;
import java.util.ArrayList;
import java.util.Collection;
import java.util.HashMap;
import java.util.Map;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.Headers;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;

/**
 * Created by andreashelms on 27/10/16.
 */

public class DeviceLocatorApp extends Application implements BootstrapNotifier, BeaconConsumer, RangeNotifier {
    private static final String TAG = "BeaconDeviceLocator";
    private RegionBootstrap regionBootstrap;
    private BeaconManager mBeaconManager;
    private OkHttpClient mHttpClient;
    private BackgroundPowerSaver mBackgroundPowerSaver;
    private double mLastDistance = 0;
    private String mLastInstance = "";
    private String mLastUrl = "";
    private static final String mServerUrl = "http://bdl.bplaced.net/";
    private HashMap<String, Double> mUrlDistanceMap;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "App started up");

        mBeaconManager = BeaconManager.getInstanceForApplication(this);
        //iBeacon-Parser
        //mBeaconManager.getBeaconParsers().add(new BeaconParser().
        //      setBeaconLayout("m:2-3=beac,i:4-19,i:20-21,i:22-23,p:24-24,d:25-25"));

        //Eddystone-URL-Parser
        mBeaconManager.getBeaconParsers().add(new BeaconParser().
                setBeaconLayout("s:0-1=feaa,m:2-2=10,p:3-3:-41,i:4-20v"));

        //Eddystone-UID-Parser
        //mBeaconManager.getBeaconParsers().add(new BeaconParser().
        //        setBeaconLayout("s:0-1=feaa,m:2-2=00,p:3-3:-41,i:4-13,i:14-19"));

        mBeaconManager.setBackgroundBetweenScanPeriod(30000l);
        mBeaconManager.setForegroundBetweenScanPeriod(2000l);

        mBackgroundPowerSaver = new BackgroundPowerSaver(this);

        //Specify Identifiers which wake up the App
        Region region = new Region("ubilabsRegion", null /* Identifier.parse("0xc43807edf6a94291846e")*/, null, null);
        regionBootstrap = new RegionBootstrap(this, region);

        mBeaconManager.bind(this);

        mHttpClient = new OkHttpClient();

        mUrlDistanceMap = new HashMap<>();
    }

    @Override
    public void didDetermineStateForRegion(int state, Region region) {
        Log.d(TAG,"I have just switched from seeing/not seeing beacons: " + state);
    }

    @Override
    public void didEnterRegion(Region region) {
        Log.d(TAG, "Got a didEnterRegion call");
        try {
            mBeaconManager.startRangingBeaconsInRegion(region);
        }
        catch (RemoteException e) {
            if (BuildConfig.DEBUG) Log.d(TAG, "Can't start ranging");
        }
    }

    @Override
    public void didExitRegion(Region region) {
        //do nothing
    }

    @Override
    public void didRangeBeaconsInRegion(Collection<Beacon> beacons, Region region) {
        setDistanceToZero(mUrlDistanceMap);
        for (final Beacon beacon: beacons) {
            if (beacon.getServiceUuid() == 0xfeaa && beacon.getBeaconTypeCode() == 0x10) {
                final String url = UrlBeaconUrlCompressor.uncompress(beacon.getId1().toByteArray());
                Log.d(TAG, "I see a beacon transmitting a url: " + url +
                        " approximately " + beacon.getDistance() + " meters away.");
                mUrlDistanceMap.put(url,beacon.getDistance());
            /*if(beacon.getServiceUuid() == 0xfeaa && beacon.getBeaconTypeCode() == 0x00) {
                Log.d(TAG, "I see a beacon with instance: " + beacon.getId2().toHexString() +
                        " approximately " + beacon.getDistance() + " meters away.");
                //Only update Database with new Location when distance to new detected location is nearer
                if((!mLastInstance.equals(beacon.getId2().toHexString()) && beacon.getDistance() < mLastDistance)
                        || mLastInstance.equals(beacon.getId2().toHexString()) || mLastDistance == 0) {
                    mLastInstance = beacon.getId2().toHexString();
                    mLastDistance = beacon.getDistance();
                    try {
                        sendLocation(mServerUrl +
                                "?l=" + beacon.getId2().toHexString() +
                                "&p=" + getPhoneName() +
                                "&n=" + getDeviceName() +
                                "&d=" + beacon.getDistance());
                    } catch (IOException e) {
                        e.printStackTrace();
                    }
                }*/
            }
        }
        for (Map.Entry<String, Double> urlDistanceEntry : mUrlDistanceMap.entrySet()) {
            try {
                sendLocation(
                        urlDistanceEntry.getKey() +
                                "&p=" + getPhoneName() +
                                "&n=" + getDeviceName() +
                                "&d=" + urlDistanceEntry.getValue()
                );
                Thread.sleep(1000);
            } catch (IOException e) {
                e.printStackTrace();
            } catch (InterruptedException e) {
                e.printStackTrace();
            }
        }

    }

    @Override
    public void onBeaconServiceConnect() {
        mBeaconManager.setRangeNotifier(this);
    }

    //send location, device- and phonename to database
    private void sendLocation(String url) throws IOException {
        Request request = new Request.Builder()
                .url(url)
                .build();

        mHttpClient.newCall(request).execute();
    }

    //get device name from current device
    public static String getDeviceName() {
        String manufacturer = Build.MANUFACTURER;
        String model = Build.MODEL;
        if (model.startsWith(manufacturer)) {
            return capitalize(model);
        }
        return capitalize(manufacturer) + " " + model;
    }

    private static String capitalize(String str) {
        if (TextUtils.isEmpty(str)) {
            return str;
        }
        char[] arr = str.toCharArray();
        boolean capitalizeNext = true;
        String phrase = "";
        for (char c : arr) {
            if (capitalizeNext && Character.isLetter(c)) {
                phrase += Character.toUpperCase(c);
                capitalizeNext = false;
                continue;
            } else if (Character.isWhitespace(c)) {
                capitalizeNext = true;
            }
            phrase += c;
        }
        return phrase;
    }

    //get bluetooth name of current device
    public String getPhoneName() {
        BluetoothAdapter myDevice = BluetoothAdapter.getDefaultAdapter();
        return myDevice.getName();
    }

    public boolean compareDistanceWithMap(double distance, HashMap<String,Double> urlDistanceMap){
        for (double mapDistance : urlDistanceMap.values()){
            if(distance < mapDistance){
                return true;
            }
        }
        return false;
    }

    public void setDistanceToZero(HashMap<String,Double> urlDistanceMap){
        for(Map.Entry<String, Double> urlDistanceEntry : urlDistanceMap.entrySet()) {
            urlDistanceMap.put(urlDistanceEntry.getKey(),0.0);
        }
    }
}

