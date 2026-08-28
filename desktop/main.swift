import Carbon.HIToolbox
import Cocoa
import UserNotifications
import WebKit

// Configuration is read from the bundle, so `takt:app` can change it without recompiling.
struct Config {
    static let info = Bundle.main.infoDictionary ?? [:]
    static let name = info["CFBundleName"] as? String ?? "Takt"
    static let root = info["TaktRoot"] as? String ?? ""
    static let php = info["TaktPhp"] as? String ?? "/usr/bin/php"
    static let port = info["TaktPort"] as? Int ?? 8000
    static let host = info["TaktHost"] as? String ?? "local.takt.de"
    /// What the window shows. The server binds to the loopback; the name resolves there.
    static var url: URL { URL(string: "http://\(host)\(port == 80 ? "" : ":\(port)")")! }
    static var loopback: URL { URL(string: "http://127.0.0.1:\(port)")! }

    /*
     * Takt answers on the local network only when the file exists — the settings write it,
     * because the shell has to know before the server starts and does not read the database.
     */
    static var networkAccess: Bool {
        root.isEmpty ? false : FileManager.default.fileExists(atPath: root + "/storage/app/network-access")
    }

    static var bind: String { networkAccess ? "0.0.0.0" : "127.0.0.1" }
}

/// Starts the local server unless something already serves the port, and stops
/// only what it started itself.
extension Notification.Name {
    static let taktToggleTimer = Notification.Name("takt.toggleTimer")
}

final class Server {
    private var process: Process?

    func responds(timeout: TimeInterval = 0.6) -> Bool {
        var request = URLRequest(url: Config.loopback)
        request.timeoutInterval = timeout
        request.httpMethod = "HEAD"

        var alive = false
        let done = DispatchSemaphore(value: 0)

        URLSession.shared.dataTask(with: request) { _, response, _ in
            alive = (response as? HTTPURLResponse) != nil
            done.signal()
        }.resume()

        _ = done.wait(timeout: .now() + timeout + 0.4)

        return alive
    }

    func start() {
        guard !responds(), !Config.root.isEmpty else { return }

        let task = Process()
        task.executableURL = URL(fileURLWithPath: Config.php)
        task.arguments = ["artisan", "serve", "--host=\(Config.bind)", "--port=\(Config.port)"]
        task.currentDirectoryURL = URL(fileURLWithPath: Config.root)

        let log = URL(fileURLWithPath: Config.root).appendingPathComponent("storage/logs/serve.log")

        if let handle = try? FileHandle(forWritingTo: log) {
            handle.seekToEndOfFile()
            task.standardOutput = handle
            task.standardError = handle
        }

        do {
            try task.run()
            process = task
        } catch {
            NSLog("Takt: server failed to start — \(error.localizedDescription)")
        }
    }

    func waitUntilReady(seconds: Double = 12) -> Bool {
        let deadline = Date().addingTimeInterval(seconds)

        while Date() < deadline {
            if responds() { return true }
            usleep(250_000)
        }

        return false
    }

    func stopIfOwned() {
        process?.terminate()
        process = nil
    }
}

/// A transparent strip along the top edge that moves the window, like a title bar would.
final class DragStrip: NSView {
    static let height: CGFloat = 44

    override var mouseDownCanMoveWindow: Bool { true }

    /*
     * The drag is started explicitly. Letting NSView handle the event would consume it,
     * and the window server never gets to move the window — which is why relying on
     * `mouseDownCanMoveWindow` alone did nothing here.
     */
    override func mouseDown(with event: NSEvent) {
        guard let window else { return }

        if event.clickCount == 2 {
            window.zoom(nil)

            return
        }

        window.performDrag(with: event)
    }
}

final class AppDelegate: NSObject, NSApplicationDelegate, WKNavigationDelegate, WKUIDelegate, WKScriptMessageHandler {
    private let server = Server()
    private var window: NSWindow!
    private var webView: WKWebView!
    private var statusItem: NSStatusItem?
    private var stateTimer: Timer?
    private var hotKey: EventHotKeyRef?
    private var awaySince: Date?

    // MARK: lifecycle

    func applicationDidFinishLaunching(_ notification: Notification) {
        buildMenu()
        buildWindow()

        server.start()

        DispatchQueue.global(qos: .userInitiated).async { [weak self] in
            let ready = self?.server.waitUntilReady() ?? false

            DispatchQueue.main.async {
                guard let self else { return }

                if ready {
                    self.webView.load(URLRequest(url: Config.url))
                } else {
                    self.showStartupFailure()
                }
            }
        }

        buildStatusItem()
        registerHotKey()
        watchForAbsence()

        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { _, _ in }
    }

    /*
     * The window may close; the app keeps running so the menu bar item stays. That is the point
     * of having one: starting the timer without opening anything.
     */
    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool { false }

    func applicationWillTerminate(_ notification: Notification) {
        stateTimer?.invalidate()
        server.stopIfOwned()
    }

    // MARK: menu bar

    private func buildStatusItem() {
        let item = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)

        item.button?.image = NSImage(systemSymbolName: "timer", accessibilityDescription: "Takt")
        item.button?.imagePosition = .imageLeading
        item.menu = statusMenu(state: nil)

        statusItem = item

        stateTimer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            self?.refreshStatusItem()
        }

        refreshStatusItem()
    }

    /** Reads the state out of the page — no second endpoint, no token, no second source. */
    private func refreshStatusItem() {
        webView?.evaluateJavaScript("JSON.stringify(window.takt?.state?.() ?? {})") { [weak self] value, _ in
            guard let self else { return }

            let state = Self.decode(value as? String)

            self.statusItem?.button?.title = Self.title(for: state)
            self.statusItem?.menu = self.statusMenu(state: state)
        }
    }

    private static func decode(_ json: String?) -> [String: Any] {
        guard let data = json?.data(using: .utf8),
              let object = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            return [:]
        }

        return object
    }

    private static func title(for state: [String: Any]) -> String {
        guard state["signedIn"] as? Bool == true else { return "" }

        let running = state["running"] as? String
        let worked = state["work"] as? Int ?? 0

        guard let running else {
            return worked > 0 ? " " + clock(worked) : ""
        }

        let since = ISO8601DateFormatter().date(from: state["since"] as? String ?? "")
        let live = worked + Int(since.map { -$0.timeIntervalSinceNow } ?? 0)

        return (running == "break" ? " ☕ " : " ") + clock(live)
    }

    private static func clock(_ seconds: Int) -> String {
        String(format: "%d:%02d", seconds / 3600, (seconds % 3600) / 60)
    }

    private func statusMenu(state: [String: Any]?) -> NSMenu {
        let menu = NSMenu()
        let running = state?["running"] as? String
        let signedIn = state?["signedIn"] as? Bool == true

        if signedIn, let running {
            menu.addItem(withTitle: running == "break" ? "Pause läuft" : "Arbeit läuft", action: nil, keyEquivalent: "")
            menu.addItem(NSMenuItem.separator())
            menu.addItem(withTitle: running == "break" ? "Zurück zur Arbeit" : "Pause starten",
                         action: running == "break" ? #selector(startWork) : #selector(startBreak), keyEquivalent: "")
            menu.addItem(withTitle: "Stoppen", action: #selector(stopTimer), keyEquivalent: "")
        } else if signedIn {
            menu.addItem(withTitle: "Kein Timer aktiv", action: nil, keyEquivalent: "")
            menu.addItem(NSMenuItem.separator())
            menu.addItem(withTitle: "Arbeit starten", action: #selector(startWork), keyEquivalent: "")
            menu.addItem(withTitle: "Pause starten", action: #selector(startBreak), keyEquivalent: "")
        } else {
            menu.addItem(withTitle: "Nicht angemeldet", action: nil, keyEquivalent: "")
        }

        menu.addItem(NSMenuItem.separator())
        menu.addItem(withTitle: "Takt öffnen", action: #selector(showWindow), keyEquivalent: "")
        menu.addItem(withTitle: "Beenden", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")

        menu.items.forEach { $0.target = $0.action == #selector(NSApplication.terminate(_:)) ? nil : self }

        return menu
    }

    @objc private func startWork() { act("window.takt?.start?.('work')") }
    @objc private func startBreak() { act("window.takt?.start?.('break')") }
    @objc private func stopTimer() { act("window.takt?.stop?.()") }

    @objc private func showWindow() {
        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
    }

    private func act(_ script: String) {
        webView?.evaluateJavaScript(script) { [weak self] _, _ in
            // the page needs a moment to post and swap before the state is worth reading again
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.8) { self?.refreshStatusItem() }
        }
    }

    // MARK: the Mac going away

    /*
     * Locking the screen and going to sleep both mean nobody is at the keyboard. macOS sends
     * these notifications on its own, so this needs no permission — and the page decides
     * whether the stretch is worth mentioning, because only it knows if a timer was running.
     */
    private func watchForAbsence() {
        let lock = DistributedNotificationCenter.default()

        lock.addObserver(forName: .init("com.apple.screenIsLocked"), object: nil, queue: .main) { [weak self] _ in
            self?.markAway()
        }

        lock.addObserver(forName: .init("com.apple.screenIsUnlocked"), object: nil, queue: .main) { [weak self] _ in
            self?.reportAway()
        }

        let workspace = NSWorkspace.shared.notificationCenter

        workspace.addObserver(forName: NSWorkspace.willSleepNotification, object: nil, queue: .main) { [weak self] _ in
            self?.markAway()
        }

        workspace.addObserver(forName: NSWorkspace.didWakeNotification, object: nil, queue: .main) { [weak self] _ in
            self?.reportAway()
        }
    }

    /** Sleep and lock often both fire; the earlier moment is the honest start of the absence. */
    private func markAway() {
        if awaySince == nil { awaySince = Date() }
    }

    private func reportAway() {
        guard let from = awaySince else { return }

        awaySince = nil

        let formatter = ISO8601DateFormatter()
        let payload = "'\(formatter.string(from: from))', '\(formatter.string(from: Date()))'"

        webView?.evaluateJavaScript(
            "window.takt?.reportAway?.(\(payload), window.takt.state().awayUrl)"
        ) { [weak self] _, _ in
            DispatchQueue.main.asyncAfter(deadline: .now() + 1.2) { self?.refreshStatusItem() }
        }
    }

    // MARK: the global shortcut

    /*
     * Carbon's hot key needs no permission at all, unlike a global event monitor, which would
     * ask for accessibility access just to start a timer.
     */
    private func registerHotKey() {
        var eventType = EventTypeSpec(eventClass: OSType(kEventClassKeyboard), eventKind: UInt32(kEventHotKeyPressed))

        InstallEventHandler(GetApplicationEventTarget(), { _, event, _ -> OSStatus in
            var pressed = EventHotKeyID()

            GetEventParameter(event, EventParamName(kEventParamDirectObject), EventParamType(typeEventHotKeyID),
                              nil, MemoryLayout<EventHotKeyID>.size, nil, &pressed)

            if pressed.id == 1 {
                NotificationCenter.default.post(name: .taktToggleTimer, object: nil)
            }

            return noErr
        }, 1, &eventType, nil, nil)

        var reference: EventHotKeyRef?
        let identifier = EventHotKeyID(signature: OSType(0x54414B54), id: 1)

        // ⌥⌘T — T for Takt, and no standard binding takes it
        RegisterEventHotKey(UInt32(kVK_ANSI_T), UInt32(optionKey | cmdKey), identifier,
                            GetApplicationEventTarget(), 0, &reference)

        hotKey = reference

        NotificationCenter.default.addObserver(forName: .taktToggleTimer, object: nil, queue: .main) { [weak self] _ in
            self?.toggleTimer()
        }
    }

    /** One key for both directions: running stops, idle starts work. */
    private func toggleTimer() {
        webView?.evaluateJavaScript("JSON.stringify(window.takt?.state?.() ?? {})") { [weak self] value, _ in
            let state = Self.decode(value as? String)

            guard state["signedIn"] as? Bool == true else {
                self?.showWindow()

                return
            }

            if state["running"] is String {
                self?.stopTimer()
            } else {
                self?.startWork()
            }
        }
    }

    // MARK: window

    private func buildWindow() {
        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .default()
        // the layout keys its app-window styling off this, no JS timing involved
        configuration.applicationNameForUserAgent = "TaktShell/1.0"
        configuration.userContentController.add(self, name: "notify")
        configuration.userContentController.addUserScript(WKUserScript(
            source: Self.notificationBridge,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        ))

        webView = WKWebView(frame: .zero, configuration: configuration)
        webView.navigationDelegate = self
        webView.uiDelegate = self
        webView.allowsBackForwardNavigationGestures = true

        window = NSWindow(
            contentRect: NSRect(x: 0, y: 0, width: 1180, height: 820),
            styleMask: [.titled, .closable, .miniaturizable, .resizable, .fullSizeContentView],
            backing: .buffered,
            defer: false
        )

        window.title = Config.name

        /*
         * No bar of its own: the traffic lights float over the app's own surface and the
         * page reserves room for them. The strip below is what makes the window draggable
         * again, since a web view swallows the mouse events a title bar would get.
         */
        window.titleVisibility = .hidden
        window.titlebarAppearsTransparent = true
        window.titlebarSeparatorStyle = .none
        window.backgroundColor = NSColor(red: 0.023, green: 0.035, blue: 0.067, alpha: 1)

        let content = NSView(frame: NSRect(x: 0, y: 0, width: 1180, height: 820))
        let strip = DragStrip()

        for view in [webView, strip] as [NSView] {
            view.translatesAutoresizingMaskIntoConstraints = false
            content.addSubview(view)
        }

        // constraints instead of autoresizing masks: unambiguous, flipped or not
        NSLayoutConstraint.activate([
            webView.leadingAnchor.constraint(equalTo: content.leadingAnchor),
            webView.trailingAnchor.constraint(equalTo: content.trailingAnchor),
            webView.topAnchor.constraint(equalTo: content.topAnchor),
            webView.bottomAnchor.constraint(equalTo: content.bottomAnchor),

            strip.leadingAnchor.constraint(equalTo: content.leadingAnchor),
            strip.trailingAnchor.constraint(equalTo: content.trailingAnchor),
            strip.topAnchor.constraint(equalTo: content.topAnchor),
            strip.heightAnchor.constraint(equalToConstant: DragStrip.height),
        ])

        window.contentView = content
        window.minSize = NSSize(width: 420, height: 560)
        window.setFrameAutosaveName("TaktMainWindow")
        window.center()
        window.makeKeyAndOrderFront(nil)

        NSApp.activate(ignoringOtherApps: true)
    }

    private func showStartupFailure() {
        let alert = NSAlert()
        alert.messageText = Config.name
        alert.informativeText = "Der lokale Server ist nicht gestartet.\n\nPrüfe storage/logs/serve.log im Projektordner."
        alert.alertStyle = .critical
        alert.addButton(withTitle: "Beenden")
        alert.runModal()
        NSApp.terminate(nil)
    }

    // MARK: navigation

    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.allow)

            return
        }

        // anything that is not the app itself belongs in the browser
        // with network access on, the LAN address is ours as well
        let ours = ["127.0.0.1", "localhost", Config.host]

        if let host = url.host, ours.contains(host) || (Config.networkAccess && host.hasPrefix("192.168.")) || url.scheme == "about" || url.scheme == "blob" {
            decisionHandler(.allow)

            return
        }

        NSWorkspace.shared.open(url)
        decisionHandler(.cancel)
    }

    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        // target="_blank" — the print view and downloads open outside
        if let url = navigationAction.request.url {
            NSWorkspace.shared.open(url)
        }

        return nil
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        webView.evaluateJavaScript("document.title") { [weak self] title, _ in
            if let title = title as? String, !title.isEmpty {
                self?.window.title = title
            }
        }
    }

    // MARK: notifications from the page

    func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
        guard let payload = message.body as? [String: Any] else { return }

        let content = UNMutableNotificationContent()
        content.title = payload["title"] as? String ?? Config.name
        content.body = payload["body"] as? String ?? ""
        content.sound = .default

        UNUserNotificationCenter.current().add(
            UNNotificationRequest(identifier: UUID().uuidString, content: content, trigger: nil)
        )
    }

    /// Web Notification API mapped onto the native centre.
    private static let notificationBridge = """
    (() => {
        const post = (title, options) => window.webkit?.messageHandlers?.notify?.postMessage({
            title: String(title ?? ''),
            body: String(options?.body ?? ''),
        });

        class NativeNotification {
            constructor(title, options) { post(title, options); }
            static requestPermission() { return Promise.resolve('granted'); }
            static get permission() { return 'granted'; }
            close() {}
            addEventListener() {}
        }

        Object.defineProperty(window, 'Notification', { value: NativeNotification, writable: false });
        document.documentElement.dataset.shell = 'native';
    })();
    """

    // MARK: menu

    @objc private func reload() { webView.reload() }
    @objc private func goBack() { webView.goBack() }
    @objc private func goForward() { webView.goForward() }
    @objc private func zoomIn() { webView.pageZoom = min(webView.pageZoom + 0.1, 2.0) }
    @objc private func zoomOut() { webView.pageZoom = max(webView.pageZoom - 0.1, 0.6) }
    @objc private func zoomReset() { webView.pageZoom = 1 }

    private func buildMenu() {
        let main = NSMenu()

        let appItem = NSMenuItem()
        let appMenu = NSMenu()
        appMenu.addItem(withTitle: "Über \(Config.name)", action: #selector(NSApplication.orderFrontStandardAboutPanel(_:)), keyEquivalent: "")
        appMenu.addItem(.separator())
        appMenu.addItem(withTitle: "\(Config.name) ausblenden", action: #selector(NSApplication.hide(_:)), keyEquivalent: "h")
        appMenu.addItem(withTitle: "Alle anzeigen", action: #selector(NSApplication.unhideAllApplications(_:)), keyEquivalent: "")
        appMenu.addItem(.separator())
        appMenu.addItem(withTitle: "\(Config.name) beenden", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
        appItem.submenu = appMenu
        main.addItem(appItem)

        let editItem = NSMenuItem()
        let editMenu = NSMenu(title: "Bearbeiten")
        editMenu.addItem(withTitle: "Widerrufen", action: Selector(("undo:")), keyEquivalent: "z")
        editMenu.addItem(withTitle: "Wiederholen", action: Selector(("redo:")), keyEquivalent: "Z")
        editMenu.addItem(.separator())
        editMenu.addItem(withTitle: "Ausschneiden", action: #selector(NSText.cut(_:)), keyEquivalent: "x")
        editMenu.addItem(withTitle: "Kopieren", action: #selector(NSText.copy(_:)), keyEquivalent: "c")
        editMenu.addItem(withTitle: "Einfügen", action: #selector(NSText.paste(_:)), keyEquivalent: "v")
        editMenu.addItem(withTitle: "Alles auswählen", action: #selector(NSText.selectAll(_:)), keyEquivalent: "a")
        editItem.submenu = editMenu
        main.addItem(editItem)

        let viewItem = NSMenuItem()
        let viewMenu = NSMenu(title: "Darstellung")
        viewMenu.addItem(withTitle: "Neu laden", action: #selector(reload), keyEquivalent: "r")
        viewMenu.addItem(.separator())
        viewMenu.addItem(withTitle: "Zurück", action: #selector(goBack), keyEquivalent: "[")
        viewMenu.addItem(withTitle: "Vorwärts", action: #selector(goForward), keyEquivalent: "]")
        viewMenu.addItem(.separator())
        viewMenu.addItem(withTitle: "Größer", action: #selector(zoomIn), keyEquivalent: "+")
        viewMenu.addItem(withTitle: "Kleiner", action: #selector(zoomOut), keyEquivalent: "-")
        viewMenu.addItem(withTitle: "Originalgröße", action: #selector(zoomReset), keyEquivalent: "0")
        viewItem.submenu = viewMenu
        main.addItem(viewItem)

        let windowItem = NSMenuItem()
        let windowMenu = NSMenu(title: "Fenster")
        windowMenu.addItem(withTitle: "Im Dock ablegen", action: #selector(NSWindow.performMiniaturize(_:)), keyEquivalent: "m")
        windowMenu.addItem(withTitle: "Zoomen", action: #selector(NSWindow.performZoom(_:)), keyEquivalent: "")
        windowItem.submenu = windowMenu
        main.addItem(windowItem)

        NSApp.mainMenu = main
        NSApp.windowsMenu = windowMenu
    }
}

let delegate = AppDelegate()
let application = NSApplication.shared
application.delegate = delegate
application.setActivationPolicy(.regular)
application.run()
