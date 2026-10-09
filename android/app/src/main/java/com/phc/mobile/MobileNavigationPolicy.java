package com.phc.mobile;

import java.net.URI;

public final class MobileNavigationPolicy {
    private MobileNavigationPolicy() {}

    public static boolean shouldShowOffline(boolean mainFrame, int status) {
        return mainFrame && status >= 500;
    }

    public static boolean isTrustedDownload(String url) {
        try {
            URI uri = URI.create(url).normalize();
            boolean http = "http".equals(uri.getScheme()) && (uri.getPort() == -1 || uri.getPort() == 80);
            boolean https = "https".equals(uri.getScheme()) && (uri.getPort() == -1 || uri.getPort() == 443);
            String path = uri.getPath();
            if (path == null || path.contains("\\")) {
                return false;
            }
            for (String segment : path.split("/")) {
                if (".".equals(segment) || "..".equals(segment)) {
                    return false;
                }
            }
            return (http || https)
                && uri.getUserInfo() == null
                && "213.6.135.115".equals(uri.getHost())
                && path.startsWith("/damage_assessment_system/");
        } catch (IllegalArgumentException | NullPointerException exception) {
            return false;
        }
    }
}
