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
    private HashMap<String, Double> mUrlDistanceMap;
    private HttpUtil mHttpUtil;

    private long scanInterval = 30000l;

    @Override
    public void onCreate() {
        super.onCreate();
        if (BuildConfig.DEBUG) Log.d(TAG, "App started up");

        mBeaconManager = BeaconManager.getInstanceForApplication(this);
        //Eddystone-URL-Parser
        mBeaconManager.getBeaconParsers().add(new BeaconParser().
                setBeaconLayout("s:0-1=feaa,m:2-2=10,p:3-3:-41,i:4-20v"));
        mBeaconManager.setBackgroundBetweenScanPeriod(scanInterval);
        mBeaconManager.bind(this);

        mBackgroundPowerSaver = new BackgroundPowerSaver(this);

        //Specify Identifiers which wake up the App
        Region region = new Region("ubilabsRegion", null, null, null);
        regionBootstrap = new RegionBootstrap(this, region);

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
        //do nothing
    }

    @Override
    public void didRangeBeaconsInRegion(Collection<Beacon> beacons, Region region) {
        int hashCode = mUrlDistanceMap.hashCode();
        //set all distances in Map to zero
        setDistanceToZero(mUrlDistanceMap);
        for (final Beacon beacon: beacons) {
            if (beacon.getServiceUuid() == 0xfeaa && beacon.getBeaconTypeCode() == 0x10) {
                final String url = UrlBeaconUrlCompressor.uncompress(beacon.getId1().toByteArray());
                if (BuildConfig.DEBUG) Log.d(TAG, "I see a beacon transmitting a url: " + url +
                        " approximately " + beacon.getDistance() + " meters away.");
                double distance =
                mUrlDistanceMap.put(url,beacon.getDistance());
            }
        }
        //only update server data when HashMap has changed
        if(mUrlDistanceMap.hashCode() != hashCode) {
            for (Map.Entry<String, Double> urlDistanceEntry : mUrlDistanceMap.entrySet()) {
                try {
                    mHttpUtil.get(
                            urlDistanceEntry.getKey() +
                                    "&p=" + DeviceUtil.getPhoneName() +
                                    "&n=" + DeviceUtil.getDeviceName() +
                                    "&d=" + urlDistanceEntry.getValue()
                    );
                } catch (IOException e) {
                    e.printStackTrace();
                }
            }
        }

    }

    @Override
    public void onBeaconServiceConnect() {
        mBeaconManager.setRangeNotifier(this);
    }

    private void setDistanceToZero(HashMap<String,Double> urlDistanceMap){
        for(Map.Entry<String, Double> urlDistanceEntry : urlDistanceMap.entrySet()) {
            urlDistanceMap.put(urlDistanceEntry.getKey(),0.0);
        }
    }
}

