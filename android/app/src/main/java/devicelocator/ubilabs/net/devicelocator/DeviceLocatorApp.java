package devicelocator.ubilabs.net.devicelocator;

import android.app.Application;
import android.os.RemoteException;
import android.util.Log;
import org.altbeacon.beacon.Beacon;
import org.altbeacon.beacon.BeaconConsumer;
import org.altbeacon.beacon.BeaconManager;
import org.altbeacon.beacon.BeaconParser;
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


/**
 * Created by andreashelms on 27/10/16.
 */

public class DeviceLocatorApp extends Application implements BootstrapNotifier, BeaconConsumer, RangeNotifier {
    private static final String TAG = "BeaconDeviceLocator";
    private RegionBootstrap regionBootstrap;
    private BeaconManager mBeaconManager;
    private BackgroundPowerSaver mBackgroundPowerSaver;
    private HashMap<String, ArrayList<Double>> mUrlDistanceMap;
    private HttpUtil mHttpUtil;
    private int hashCode = 0;
    private Region mRegion;
    private int mScanCount = 0;

    private long scanPeriod = 5000l;
    private long scanInterval = 1000l;
    private int sendInterval = 100;

    @Override
    public void onCreate() {
        super.onCreate();
        if (BuildConfig.DEBUG) Log.d(TAG, "App started up");
        mBackgroundPowerSaver = new BackgroundPowerSaver(this);

        mBeaconManager = BeaconManager.getInstanceForApplication(this);
        //Eddystone-URL-Parser
        mBeaconManager.getBeaconParsers().add(new BeaconParser().
                setBeaconLayout("s:0-1=feaa,m:2-2=10,p:3-3:-41,i:4-20v"));
        mBeaconManager.setBackgroundScanPeriod(scanPeriod);
        mBeaconManager.setBackgroundBetweenScanPeriod(scanInterval);
        mBeaconManager.setForegroundScanPeriod(scanPeriod);
        mBeaconManager.setForegroundBetweenScanPeriod(scanInterval);

        mRegion = new Region("ubilabsRegion", null, null, null);
        regionBootstrap = new RegionBootstrap(this, mRegion);

        mBeaconManager.bind(this);

        mUrlDistanceMap = new HashMap<>();

        mHttpUtil = new HttpUtil();
    }

    @Override
    public void didDetermineStateForRegion(int state, Region region) {
        //I have just switched from seeing/not seeing beacons
    }

    @Override
    public void didEnterRegion(Region region) {
        if (BuildConfig.DEBUG) Log.d(TAG, "Got a didEnterRegion call");
        try {
            mBeaconManager.startRangingBeaconsInRegion(region);
        }
        catch (RemoteException e) {
            if (BuildConfig.DEBUG) Log.d(TAG, "Can't start ranging");
        }
    }

    @Override
    public void didExitRegion(Region region) {

    }

    @Override
    public void didRangeBeaconsInRegion(Collection<Beacon> beacons, Region region) {
        if(beacons.hashCode() != hashCode) {
            hashCode = beacons.hashCode();
            for (final Beacon beacon : beacons) {
                if (beacons.size() > 0) {
                    Log.i(TAG, "The first beacon (" + beacon.getBluetoothAddress() + ") I see is about "+beacon.getDistance()+" meters away.");
                    beacons.iterator().next().getRssi();
                }
                if (beacon.getServiceUuid() == 0xfeaa && beacon.getBeaconTypeCode() == 0x10) {
                    final String url = UrlBeaconUrlCompressor.uncompress(beacon.getId1().toByteArray());

                    if (!mUrlDistanceMap.containsKey(url)) {
                        mUrlDistanceMap.put(url, new ArrayList<Double>());
                    }

                    ArrayList<Double> distanceList = mUrlDistanceMap.get(url);

                    double distance = calculateAccuracy(beacon.getTxPower(), beacon.getRssi());
                    distanceList.add(distance);
                    mScanCount++;

                    if (BuildConfig.DEBUG) Log.d(TAG, "I see a beacon transmitting a url: " + url +
                            " approximately " + calculateAccuracy(beacon.getTxPower(), beacon.getRssi()) + " meters away. (count: " + mScanCount + ")");
                }
            }
            if(mScanCount >= sendInterval) {
                mScanCount = 0;
                for (Map.Entry<String, ArrayList<Double>> urlDistanceEntry : mUrlDistanceMap.entrySet()) {
                    double distance = 0;
                    int count = 0;
                    for (double urlDistance : urlDistanceEntry.getValue()) {
                        count++;
                        distance = distance + urlDistance;
                    }
                    Log.d(TAG, urlDistanceEntry.getKey() + ":" + count);
                    distance = distance / urlDistanceEntry.getValue().size();
                    String url = null;
                    try {
                        url = HttpUtil.expandUrl(urlDistanceEntry.getKey());
                        if(url == null) {
                            url = urlDistanceEntry.getKey();
                        }
                        mHttpUtil.get(
                                url +
                                        "&name=" + DeviceUtil.getPhoneName() +
                                        "&mac=" + DeviceUtil.getMacAddress() +
                                        "&distance=" + distance
                        );
                        mUrlDistanceMap.put(urlDistanceEntry.getKey(), new ArrayList<Double>());
                    } catch (IOException e) {
                        e.printStackTrace();
                    }
                }
            }
        }
    }

    @Override
    public void onBeaconServiceConnect() {
        mBeaconManager.addRangeNotifier(this);
        try {
            mBeaconManager.startRangingBeaconsInRegion(mRegion);
        }
        catch (RemoteException e) {
            if (BuildConfig.DEBUG) Log.d(TAG, "Can't start ranging");
        }
    }

    protected static double calculateAccuracy(double txPower, double rssi) {
        return Math.pow(10d, (txPower - rssi) / (10 * 2));
    }
}

