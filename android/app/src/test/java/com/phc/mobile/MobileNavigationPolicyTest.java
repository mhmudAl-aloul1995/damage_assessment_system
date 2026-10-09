package com.phc.mobile;

import org.junit.Test;
import static org.junit.Assert.*;

public class MobileNavigationPolicyTest {
    @Test
    public void retainsPermissionAndValidationResponsesInsteadOfShowingOffline() {
        for (int status : new int[]{200, 401, 403, 404, 419, 422}) {
            assertFalse(MobileNavigationPolicy.shouldShowOffline(true, status));
        }
        assertTrue(MobileNavigationPolicy.shouldShowOffline(true, 503));
        assertFalse(MobileNavigationPolicy.shouldShowOffline(false, 503));
    }

    @Test
    public void attachesSessionCookiesOnlyToTrustedDownloads() {
        assertTrue(MobileNavigationPolicy.isTrustedDownload("http://213.6.135.115/damage_assessment_system/report.pdf?id=1"));
        assertTrue(MobileNavigationPolicy.isTrustedDownload("https://213.6.135.115/damage_assessment_system/report.pdf"));
        for (String url : new String[]{
            "http://213.6.135.115.evil.example/damage_assessment_system/report.pdf",
            "http://example.com/damage_assessment_system/report.pdf",
            "http://213.6.135.115/other/report.pdf",
            "http://213.6.135.115/damage_assessment_system/../other/report.pdf",
            "http://213.6.135.115/damage_assessment_system/%2e%2e/other/report.pdf",
            "http://user@213.6.135.115/damage_assessment_system/report.pdf",
            "http://213.6.135.115:8080/damage_assessment_system/report.pdf",
            "file:///damage_assessment_system/report.pdf", "not a URL", null
        }) {
            assertFalse(MobileNavigationPolicy.isTrustedDownload(url));
        }
    }
}
