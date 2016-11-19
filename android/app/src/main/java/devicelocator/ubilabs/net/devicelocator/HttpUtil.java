package devicelocator.ubilabs.net.devicelocator;

import android.util.Log;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.Proxy;
import java.net.URL;

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
        Log.d(TAG,"URL sent: " + url);
        Response response = mHttpClient.newCall(request).execute();
        response.close();

    }

    //expands shortened url and returns it
    public static String expandUrl(String address) throws IOException {
        URL url = new URL(address);

        HttpURLConnection connection = (HttpURLConnection) url.openConnection(Proxy.NO_PROXY);
        connection.setInstanceFollowRedirects(false);
        connection.connect();
        String expandedURL = connection.getHeaderField("Location");
        connection.getInputStream().close();
        return expandedURL;
    }
}
