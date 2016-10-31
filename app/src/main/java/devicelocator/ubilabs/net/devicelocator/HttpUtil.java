package devicelocator.ubilabs.net.devicelocator;

import android.util.Log;

import java.io.IOException;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;

/**
 * Created by andreashelms on 31/10/16.
 */

public class HttpUtil {

    private static final String TAG = HttpUtil.class.getName();
    private OkHttpClient mHttpClient;

    public HttpUtil(){
        mHttpClient = new OkHttpClient();
    }

    //send location, device- and phonename to database
    public void get(final String url) throws IOException {
        Request request = new Request.Builder()
                .url(url)
                .build();

        mHttpClient.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(final Call call, IOException e) {
                e.printStackTrace();
                if (BuildConfig.DEBUG) Log.d(TAG, "Fail:" + e.toString());
            }

            @Override
            public void onResponse(Call call, final Response response) throws IOException {
                if (BuildConfig.DEBUG) Log.d(TAG, "Success calling " + url);
                response.close();
            }
        });
    }
}
