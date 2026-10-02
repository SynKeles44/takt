import Carbon.HIToolbox
import Cocoa
import EventKit
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
    private let calendarStore = EKEventStore()
    private var trailApp: (name: String, title: String?, since: Date)?
    private var trailSpans: [[String: String]] = []
    private var trailTimer: Timer?

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
        readCalendar()
        watchActivity()

        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { _, _ in }
    }

    /*
     * The window may close; the app keeps running so the menu bar item stays. That is the point
     * of having one: starting the timer without opening anything.
     */
    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool { false }

    func applicationWillTerminate(_ notification: Notification) {
        flushTrail()
        trailTimer?.invalidate()
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
        /*
         * The focused ticket next to the clock — the whole point of a focus is answering "what am
         * I on" without opening the window. Only while a timer runs: a key sitting in the menu bar
         * on an idle day is noise, not information.
         */
        let ticket = state["ticket"] as? String

        guard let running else {
            return worked > 0 ? " " + clock(worked) : ""
        }

        let since = ISO8601DateFormatter().date(from: state["since"] as? String ?? "")
        let live = worked + Int(since.map { -$0.timeIntervalSinceNow } ?? 0)
        let prefix = running == "break" ? " ☕ " : " "

        if running == "work", let key = ticket, !key.isEmpty {
            return prefix + clock(live) + " · " + key
        }

        return prefix + clock(live)
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

            // the title is capped in the page, so a long ticket cannot stretch the menu
            if let key = state?["ticket"] as? String, !key.isEmpty {
                let title = state?["ticketTitle"] as? String ?? ""

                menu.addItem(withTitle: title.isEmpty ? key : key + " — " + title, action: nil, keyEquivalent: "")
            }

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

    // MARK: the activity trail

    /*
     * Which application is in front, and for how long. NSWorkspace says which one without any
     * permission; the window title needs accessibility access, so it is asked for only if it
     * was already granted — the trail works without it, with the application name alone.
     */
    private func watchActivity() {
        NSWorkspace.shared.notificationCenter.addObserver(
            forName: NSWorkspace.didActivateApplicationNotification, object: nil, queue: .main
        ) { [weak self] note in
            let app = note.userInfo?[NSWorkspace.applicationUserInfoKey] as? NSRunningApplication

            self?.switchTrail(to: app?.localizedName ?? "unbekannt", pid: app?.processIdentifier)
        }

        trailTimer = Timer.scheduledTimer(withTimeInterval: 120, repeats: true) { [weak self] _ in
            self?.flushTrail()
        }
    }

    private func switchTrail(to name: String, pid: pid_t?) {
        closeTrailSpan()

        trailApp = (name: name, title: windowTitle(of: pid), since: Date())
    }

    private func closeTrailSpan() {
        guard let current = trailApp else { return }

        let formatter = ISO8601DateFormatter()

        trailSpans.append([
            "app": current.name,
            "title": current.title ?? "",
            "starts_at": formatter.string(from: current.since),
            "ends_at": formatter.string(from: Date()),
        ])

        trailApp = nil
    }

    /** Only asks when the permission is already there — Takt never nags for it. */
    private func windowTitle(of pid: pid_t?) -> String? {
        guard let pid, AXIsProcessTrusted() else { return nil }

        let element = AXUIElementCreateApplication(pid)
        var window: CFTypeRef?

        guard AXUIElementCopyAttributeValue(element, kAXFocusedWindowAttribute as CFString, &window) == .success,
              let focused = window else { return nil }

        var title: CFTypeRef?

        guard AXUIElementCopyAttributeValue(focused as! AXUIElement, kAXTitleAttribute as CFString, &title) == .success else {
            return nil
        }

        return title as? String
    }

    private func flushTrail() {
        closeTrailSpan()

        guard !trailSpans.isEmpty,
              let payload = try? JSONSerialization.data(withJSONObject: trailSpans),
              let json = String(data: payload, encoding: .utf8) else {
            // keep what could not be encoded out of the way rather than retrying it forever
            trailSpans = []

            return
        }

        trailSpans = []

        webView?.evaluateJavaScript("window.takt?.reportActivity?.(\(json))")

        // the trail continues with whatever is in front right now
        let front = NSWorkspace.shared.frontmostApplication

        trailApp = (name: front?.localizedName ?? "unbekannt",
                    title: windowTitle(of: front?.processIdentifier),
                    since: Date())
    }

    // MARK: the calendar

    /*
     * The Mac's own calendars, handed to the page as proposals — Takt books nothing on its own.
     * macOS asks for permission once; a refusal simply means the widget stays empty and says so.
     */
    private func readCalendar() {
        let handler: (Bool, Error?) -> Void = { [weak self] granted, _ in
            guard granted else { return }

            DispatchQueue.main.asyncAfter(deadline: .now() + 2) { self?.postTodaysEvents() }
        }

        if #available(macOS 14.0, *) {
            calendarStore.requestFullAccessToEvents(completion: handler)
        } else {
            calendarStore.requestAccess(to: .event, completion: handler)
        }
    }

    private func postTodaysEvents() {
        let start = Calendar.current.startOfDay(for: Date())

        guard let end = Calendar.current.date(byAdding: .day, value: 1, to: start) else { return }

        let predicate = calendarStore.predicateForEvents(withStart: start, end: end, calendars: nil)
        let formatter = ISO8601DateFormatter()

        let events: [[String: String]] = calendarStore.events(matching: predicate)
            .filter { !$0.isAllDay }
            .compactMap { event in
                guard let from = event.startDate, let to = event.endDate else { return nil }

                return [
                    "title": event.title ?? "",
                    "starts_at": formatter.string(from: from),
                    "ends_at": formatter.string(from: to),
                    "calendar": event.calendar?.title ?? "",
                ]
            }

        guard let payload = try? JSONSerialization.data(withJSONObject: events),
              let json = String(data: payload, encoding: .utf8) else { return }

        let day = DateFormatter()
        day.dateFormat = "yyyy-MM-dd"

        webView?.evaluateJavaScript(
            "window.takt?.reportCalendar?.('\(day.string(from: start))', \(json))"
        )
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
            self?.postTodaysEvents()
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
        configuration.userContentController.add(self, name: "canvas")
        configuration.userContentController.addUserScript(WKUserScript(
            source: Self.notificationBridge,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        ))

        webView = WKWebView(frame: .zero, configuration: configuration)
        webView.navigationDelegate = self
        webView.uiDelegate = self
        webView.allowsBackForwardNavigationGestures = true

        /*
         * The web view paints its own white (or system grey) between committing a navigation and
         * the page's first paint — on a ticket page that is a second of the wrong colour. With its
         * own background off, the window shows through, and the window wears the page's canvas
         * colour: the page reports it on every load, the last value is kept for the next launch.
         */
        webView.setValue(false, forKey: "drawsBackground")
        paintCanvas(Self.storedCanvas())

        // invisible until the first page has painted: the window's canvas colour shows instead of
        // the web view's own white, which the switch above no longer prevents on current WebKit
        webView.alphaValue = 0

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
        window.backgroundColor = Self.storedCanvas()

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
        if webView.alphaValue < 1 {
            webView.alphaValue = 1
        }

        webView.evaluateJavaScript("document.title") { [weak self] title, _ in
            if let title = title as? String, !title.isEmpty {
                self?.window.title = title
            }
        }

        if UserDefaults.standard.bool(forKey: "takt.debugSnapshots") {
            // what the engine inside this window actually supports — written next to the frames
            webView.evaluateJavaScript("JSON.stringify({ sameDocument: 'startViewTransition' in document, crossDocument: typeof CSSViewTransitionRule !== 'undefined', pageReveal: 'onpagereveal' in window, scheme: getComputedStyle(document.documentElement).colorScheme, canvas: getComputedStyle(document.documentElement).backgroundColor, url: location.pathname })") { value, _ in
                let folder = FileManager.default.homeDirectoryForCurrentUser.appendingPathComponent("Library/Logs/Takt/snapshots", isDirectory: true)
                try? FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
                try? ((value as? String ?? "") + "\n").data(using: .utf8)?.write(to: folder.appendingPathComponent("engine.txt"))
            }
        }
    }

    // MARK: snapshot series — `defaults write de.takt.app takt.debugSnapshots -bool true`

    /*
     * What the window shows during a navigation cannot be observed from outside the app: a
     * browser's engine composites differently, and the screen cannot be recorded without a
     * permission the shell does not have. So the web view photographs itself — one frame every
     * 50 ms for a second after each navigation starts — into ~/Library/Logs/Takt/snapshots. Off
     * unless the default above is set; it costs nothing when it is not.
     */
    private var snapshotRun = 0

    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        guard UserDefaults.standard.bool(forKey: "takt.debugSnapshots") else { return }

        snapshotRun += 1

        let run = snapshotRun
        let folder = FileManager.default.homeDirectoryForCurrentUser.appendingPathComponent("Library/Logs/Takt/snapshots", isDirectory: true)

        try? FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)

        let started = Date()

        for frame in 0..<20 {
            DispatchQueue.main.asyncAfter(deadline: .now() + .milliseconds(frame * 50)) { [weak self] in
                guard let self, let webView = self.webView else { return }

                let configuration = WKSnapshotConfiguration()
                configuration.afterScreenUpdates = false

                webView.takeSnapshot(with: configuration) { image, _ in
                    guard let image, let tiff = image.tiffRepresentation,
                          let bitmap = NSBitmapImageRep(data: tiff),
                          let png = bitmap.representation(using: .png, properties: [:]) else { return }

                    let elapsed = Int(Date().timeIntervalSince(started) * 1000)
                    let file = folder.appendingPathComponent(String(format: "run%02d-%02d-%04dms.png", run, frame, elapsed))

                    try? png.write(to: file)
                }
            }
        }
    }

    // MARK: the page's canvas colour

    private static let canvasKey = "takt.canvas"

    /// Midnight until the page has said otherwise once.
    private static func storedCanvas() -> NSColor {
        Self.color(from: UserDefaults.standard.string(forKey: canvasKey) ?? "") ?? NSColor(red: 0.023, green: 0.035, blue: 0.067, alpha: 1)
    }

    /// Parses what getComputedStyle hands over: `rgb(6, 9, 17)`, `rgba(…)`, or a `#rrggbb` hex.
    private static func color(from text: String) -> NSColor? {
        let value = text.trimmingCharacters(in: .whitespacesAndNewlines)

        if value.hasPrefix("#"), value.count == 7, let number = UInt32(value.dropFirst(), radix: 16) {
            return NSColor(
                red: CGFloat((number >> 16) & 0xff) / 255,
                green: CGFloat((number >> 8) & 0xff) / 255,
                blue: CGFloat(number & 0xff) / 255,
                alpha: 1
            )
        }

        guard value.hasPrefix("rgb") else { return nil }

        let parts = value
            .drop(while: { $0 != "(" }).dropFirst()
            .split(whereSeparator: { $0 == "," || $0 == " " || $0 == "/" || $0 == ")" })
            .compactMap { Double($0) }

        guard parts.count >= 3 else { return nil }

        return NSColor(red: parts[0] / 255, green: parts[1] / 255, blue: parts[2] / 255, alpha: 1)
    }

    private func paintCanvas(_ color: NSColor) {
        window?.backgroundColor = color

        if #available(macOS 12.0, *) {
            webView?.underPageBackgroundColor = color
        }
    }

    // MARK: notifications from the page

    func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
        if message.name == "canvas" {
            guard let text = message.body as? String, let color = Self.color(from: text) else { return }

            paintCanvas(color)

            if let rgb = color.usingColorSpace(.sRGB) {
                let hex = String(format: "#%02x%02x%02x", Int(rgb.redComponent * 255), Int(rgb.greenComponent * 255), Int(rgb.blueComponent * 255))
                UserDefaults.standard.set(hex, forKey: Self.canvasKey)
            }

            return
        }

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
