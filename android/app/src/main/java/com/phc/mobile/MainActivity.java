package com.phc.mobile;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;
import com.getcapacitor.WebViewListener;
import android.app.DownloadManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.URLUtil;
import android.webkit.WebView;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.Toast;
import androidx.activity.OnBackPressedCallback;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (bridge == null) {
            return;
        }

        WebView webView = bridge.getWebView();
        bridge.setWebViewClient(new BridgeWebViewClient(bridge) {
            @Override
            public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
                if (MobileNavigationPolicy.shouldShowOffline(request.isForMainFrame(), response.getStatusCode())) {
                    super.onReceivedHttpError(view, request, response);
                }
            }
        });
        ViewGroup parent = (ViewGroup) webView.getParent();
        parent.removeView(webView);
        LinearLayout container = new LinearLayout(this);
        container.setOrientation(LinearLayout.VERTICAL);
        container.setBackgroundColor(Color.rgb(245, 247, 246));
        parent.addView(container, new ViewGroup.LayoutParams(-1, -1));

        ProgressBar progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setIndeterminate(true);
        container.addView(progress, new LinearLayout.LayoutParams(-1, dp(3)));
        container.addView(webView, new LinearLayout.LayoutParams(-1, 0, 1));

        LinearLayout toolbar = new LinearLayout(this);
        toolbar.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        toolbar.setPadding(dp(10), dp(4), dp(10), dp(4));
        toolbar.setBackgroundColor(Color.WHITE);
        Button home = navigationButton(R.string.mobile_home);
        Button back = navigationButton(R.string.mobile_back);
        Button refresh = navigationButton(R.string.mobile_refresh);
        for (Button button : new Button[]{home, back, refresh}) {
            toolbar.addView(button, new LinearLayout.LayoutParams(0, dp(48), 1));
        }
        container.addView(toolbar);
        home.setOnClickListener(view -> bridge.reload());
        back.setOnClickListener(view -> {
            if (webView.canGoBack()) {
                webView.goBack();
            }
        });
        refresh.setOnClickListener(view -> webView.reload());
        bridge.addWebViewListener(new WebViewListener() {
            @Override
            public void onPageStarted(WebView view) {
                progress.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageLoaded(WebView view) {
                progress.setVisibility(View.INVISIBLE);
                back.setEnabled(view.canGoBack());
            }
        });
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    moveTaskToBack(true);
                }
            }
        });
        ViewCompat.setOnApplyWindowInsetsListener(container, (view, insets) -> {
            androidx.core.graphics.Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.ime());
            view.setPadding(bars.left, bars.top, bars.right, bars.bottom);
            return WindowInsetsCompat.CONSUMED;
        });
        ViewCompat.requestApplyInsets(container);
        webView.setDownloadListener((url, userAgent, disposition, mimeType, length) -> {
            Uri uri = Uri.parse(url);
            if (!MobileNavigationPolicy.isTrustedDownload(url)) {
                Toast.makeText(this, R.string.download_failed, Toast.LENGTH_LONG).show();
                return;
            }
            try {
                DownloadManager.Request download = new DownloadManager.Request(uri);
                String cookie = CookieManager.getInstance().getCookie(url);
                if (cookie != null) {
                    download.addRequestHeader("Cookie", cookie);
                }
                download.addRequestHeader("User-Agent", userAgent);
                String filename = URLUtil.guessFileName(url, disposition, mimeType);
                download.setTitle(filename);
                download.setMimeType(mimeType);
                download.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                download.setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, filename);
                ((DownloadManager) getSystemService(DOWNLOAD_SERVICE)).enqueue(download);
                Toast.makeText(this, R.string.download_started, Toast.LENGTH_LONG).show();
            } catch (RuntimeException exception) {
                Toast.makeText(this, R.string.download_failed, Toast.LENGTH_LONG).show();
            }
        });
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private Button navigationButton(int title) {
        Button button = new Button(this);
        button.setText(title);
        button.setTextSize(13);
        button.setTextColor(Color.rgb(21, 63, 54));
        button.setBackgroundColor(Color.TRANSPARENT);
        button.setAllCaps(false);
        return button;
    }
}
