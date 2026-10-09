import UIKit
import Capacitor
import WebKit

@UIApplicationMain
class AppDelegate: UIResponder, UIApplicationDelegate {

    var window: UIWindow?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        // Override point for customization after application launch.
        return true
    }

    func applicationWillResignActive(_ application: UIApplication) {
        // Sent when the application is about to move from active to inactive state. This can occur for certain types of temporary interruptions (such as an incoming phone call or SMS message) or when the user quits the application and it begins the transition to the background state.
        // Use this method to pause ongoing tasks, disable timers, and invalidate graphics rendering callbacks. Games should use this method to pause the game.
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        // Use this method to release shared resources, save user data, invalidate timers, and store enough application state information to restore your application to its current state in case it is terminated later.
        // If your application supports background execution, this method is called instead of applicationWillTerminate: when the user quits.
    }

    func applicationWillEnterForeground(_ application: UIApplication) {
        // Called as part of the transition from the background to the active state; here you can undo many of the changes made on entering the background.
    }

    func applicationDidBecomeActive(_ application: UIApplication) {
        // Restart any tasks that were paused (or not yet started) while the application was inactive. If the application was previously in the background, optionally refresh the user interface.
    }

    func applicationWillTerminate(_ application: UIApplication) {
        // Called when the application is about to terminate. Save data if appropriate. See also applicationDidEnterBackground:.
    }

    func application(_ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]) -> Bool {
        // Called when the app was launched with a url. Feel free to add additional processing here,
        // but if you want the App API to support tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(app, open: url, options: options)
    }

    func application(_ application: UIApplication, continue userActivity: NSUserActivity, restorationHandler: @escaping ([UIUserActivityRestoring]?) -> Void) -> Bool {
        // Called when the app was launched with an activity, including Universal Links.
        // Feel free to add additional processing here, but if you want the App API to support
        // tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(application, continue: userActivity, restorationHandler: restorationHandler)
    }

}

class PHCViewController: CAPBridgeViewController {
    private var loadingObservation: NSKeyValueObservation?
    private var backObservation: NSKeyValueObservation?

    override func viewDidLoad() {
        super.viewDidLoad()
        guard let browser = webView else { return }
        let container = UIView()
        container.backgroundColor = UIColor(red: 0.96, green: 0.97, blue: 0.96, alpha: 1)
        view = container
        browser.translatesAutoresizingMaskIntoConstraints = false
        container.addSubview(browser)

        let toolbar = UIStackView()
        toolbar.axis = .horizontal
        toolbar.distribution = .fillEqually
        toolbar.semanticContentAttribute = .forceRightToLeft
        toolbar.backgroundColor = .white
        toolbar.translatesAutoresizingMaskIntoConstraints = false
        let home = button("الرئيسية", action: #selector(goHome))
        let back = button("رجوع", action: #selector(goBack))
        let refresh = button("تحديث", action: #selector(refreshPage))
        [home, back, refresh].forEach { toolbar.addArrangedSubview($0) }
        container.addSubview(toolbar)

        let progress = UIProgressView(progressViewStyle: .bar)
        progress.tintColor = UIColor(red: 0.08, green: 0.25, blue: 0.21, alpha: 1)
        progress.translatesAutoresizingMaskIntoConstraints = false
        container.addSubview(progress)
        NSLayoutConstraint.activate([
            progress.topAnchor.constraint(equalTo: container.safeAreaLayoutGuide.topAnchor),
            progress.leadingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.leadingAnchor),
            progress.trailingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.trailingAnchor),
            browser.topAnchor.constraint(equalTo: progress.bottomAnchor),
            browser.leadingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.leadingAnchor),
            browser.trailingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.trailingAnchor),
            browser.bottomAnchor.constraint(equalTo: toolbar.topAnchor),
            toolbar.leadingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.leadingAnchor),
            toolbar.trailingAnchor.constraint(equalTo: container.safeAreaLayoutGuide.trailingAnchor),
            toolbar.bottomAnchor.constraint(equalTo: container.safeAreaLayoutGuide.bottomAnchor),
            toolbar.heightAnchor.constraint(equalToConstant: 54)
        ])
        browser.allowsBackForwardNavigationGestures = true
        loadingObservation = browser.observe(\.estimatedProgress, options: [.initial, .new]) { browser, _ in
            progress.progress = Float(browser.estimatedProgress)
            progress.isHidden = browser.estimatedProgress >= 1
        }
        backObservation = browser.observe(\.canGoBack, options: [.initial, .new]) { browser, _ in
            back.isEnabled = browser.canGoBack
        }
    }

    private func button(_ title: String, action: Selector) -> UIButton {
        let result = UIButton(type: .system)
        result.setTitle(title, for: .normal)
        result.tintColor = UIColor(red: 0.08, green: 0.25, blue: 0.21, alpha: 1)
        result.addTarget(self, action: action, for: .touchUpInside)
        return result
    }

    @objc private func goHome() {
        guard let url = bridge?.config.localURL else { return }
        webView?.load(URLRequest(url: url))
    }

    @objc private func goBack() { webView?.goBack() }
    @objc private func refreshPage() { webView?.reload() }
}
