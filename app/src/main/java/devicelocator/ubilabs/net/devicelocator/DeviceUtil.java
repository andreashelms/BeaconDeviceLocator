package devicelocator.ubilabs.net.devicelocator;

import android.bluetooth.BluetoothAdapter;
import android.location.Location;
import android.os.Build;
import android.text.TextUtils;

/**
 * Created by andreashelms on 31/10/16.
 */

public class DeviceUtil {

    //get device mac address of bluetooth adapter
    public static String getMacAddress(){
        BluetoothAdapter myDevice = BluetoothAdapter.getDefaultAdapter();
        String macAddress = myDevice.getAddress();
        if(macAddress.equals("")){
            return "UNKNOWN";
        }else{
            return macAddress;
        }
    }

    //get bluetooth name of current device
    public static String getPhoneName() {
        BluetoothAdapter myDevice = BluetoothAdapter.getDefaultAdapter();
        return myDevice.getName();
    }
}
