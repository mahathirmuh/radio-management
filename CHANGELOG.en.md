# Changelog (English)

The notes of [CHANGELOG.md](CHANGELOG.md) in English, newest first: the Version history dialog (the version number in
the footer) shows them when the app is in English. CHANGELOG.md is the reference: it has the same versions, and for each
one what to do on the server (**Deploy**) and its link.

## [1.21.0] - 2026-10-21

### Changed

- The **Version history** follows the chosen language: in English, the notes of every version are in English too. They
  come from the new file `CHANGELOG.en.md`, the English translation of `CHANGELOG.md` for every version; every release
  from now on is written in both files.

### Fixed

- The label of each kind of change above the notes of a version (for example **CHANGED**) is in the language of the
  page, like the legend. It used to be always in Indonesian.

## [1.20.1] - 2026-10-21

### Removed

- Leftovers of the Base-Apps-Merdeka template that this app never used. The app looks and works the same.
  - Every `x-global` Blade component except `btn-detail` and `summary-card` (18 files).
  - The `tinymce`, `bootstrap` and `list.js` libraries in `public/assets/libs/` (about 9 MB).
  - The `mSafe` and `orangeHR` models, with their `orangeHr` and `mSafe` database connections in `config/database.php`.
    The `DB_*_ORANGEHR` and `DB_*_MSAFE` settings are no longer read; neither was ever in `.env.example`.

## [1.20.0] - 2026-10-21

### Changed

- The **Version history** dialog (the version number in the footer) now follows the design handoff: a timeline, newest
  first.
  - Each series (`1.20`, `1.19`, …) is one node labelled with its number, on its minor release (`.0`); its patch
    releases branch off below it with a small dot.
  - Each version takes the colour of its strongest kind of change: Removed red, Security violet, Added green, Fixed
    orange, Changed blue, Deprecated grey. Each part of its notes starts with the label of its kind in the same colour.
  - A legend at the top: minor release, patch release, and the kinds of change there are.
  - A **Version 1** band where a major version begins, and a **Major release** label on its `N.0.0` version.
  - The running version is marked **This version**, and its node pulses slowly (unless the system asks for reduced
    motion).

## [1.19.1] - 2026-10-21

### Fixed

- The **Rows per page** menu below a table (10, 25, 50, 100): its choices no longer stretch out of their box over the
  table. The theme gives every menu item a width of at least 176 px, while this menu is only 84 px wide.

## [1.19.0] - 2026-10-21

### Changed

- `GET /api/v1/health` now checks that the website works normally all the way to its database, also without a token:
  - `web`: the login page is opened as a browser would, through routing, the session, the language and the template,
    and must show its form;
  - `database`: the database answers, and the app's tables (`users`, `menu`, `radios`) can be read;
  - `storage`;
  - `session`: a session can be saved and read back.

  The status of all four is shown in the answer without a token, and a single failure means `down` (HTTP 503). With a
  token, every check is shown in full as before.

## [1.18.0] - 2026-10-21

### Added

- Health checks:
  - **`GET /up`**: the app answers, without the database, a session or a token. For the container healthcheck and
    uptime monitors.
  - **`GET /api/v1/health`** without a token: the status of the app (database and storage) and its version, HTTP 503
    when it is not working.
  - **`GET /api/v1/health`** with an API token: details per service, that is the database, storage, cache, migrations
    not yet run (by name), Snipe-IT, Microsoft, Active Directory and the HR database. Each check is `ok`, `warn`,
    `fail` or `skipped`, with its time and reason. The overall status is `degraded` when a supporting service has a
    problem. Server names and error messages from servers are never shown.

### Changed

- Docker: the container healthcheck now calls `/up` instead of the login page.

## [1.17.0] - 2026-10-21

### Added

- Snipe-IT, Assets: a picture of each asset beside its asset tag (the picture of the asset or of its model in Snipe-IT;
  without one, the icon of its category), with the name of the asset below the asset tag.
- Snipe-IT, Assets: a dedicated **See the details** button (an eye icon) at the end of each row, to open the details of
  the asset.
- Asset details: a large **Picture** panel; click the picture to open it at full size.

### Changed

- Snipe-IT, Assets: the Name column is merged into the Asset tag column (as its second line), and the asset tag is no
  longer a link: the details open with their button.

## [1.16.0] - 2026-10-21

### Added

- Snipe-IT: **asset details**. An asset tag in the Assets list (and an asset in the Activity log and Maintenance) opens
  its details page in this app, with:
  - the asset's data, its holder and location;
  - purchase and warranty (purchase cost, book value, warranty left, age);
  - custom fields (for example Radio ID and MDC Code), notes;
  - its full history in Snipe-IT (checkouts, checkins, changes old → new);
  - maintenance, licenses, assets checked out to this asset, and audit dates.

  The **Open in Snipe-IT** button stays, for components and files, which the Snipe-IT 6.2 API does not provide.

### Changed

- Snipe-IT, Activity log: the Changes column uses readable field names and values ("Status: Ready to Deploy →
  Deployed", "Location: Plant A") instead of `status_id: 2 → 4`.

### Security

- Custom fields whose name marks a secret (password, PIN, BitLocker, key) are not shown, nor are password values in the
  history. License keys are still never sent to the browser.

## [1.15.0] - 2026-10-21

### Added

- A new **Snipe-IT** menu in the sidebar (section "IT Assets"), to look at what Snipe-IT holds without opening it, with
  submenus:
  - **Overview**: the count of every list in Snipe-IT, assets per status, and assets per category;
  - **Assets**: every asset (asset tag, SN, model, category, status, holder, location), with category and status
    filters;
  - **Catalog**: models, categories, manufacturers, statuses, suppliers, custom fields, fieldsets, depreciations;
  - **People & Organization**: users, departments, locations, companies, groups;
  - **Licenses & Stock**: licenses, accessories, consumables, components, kits;
  - **History**: the activity log (checkouts, checkins, changes) and maintenance.

  Every list can be searched, sorted and paged; each row links to its page in Snipe-IT. The menu is for Super Admin
  only; other roles can be given it in Role & Permission.

### Security

- The Snipe-IT menu only reads: the app never sends anything but GET to Snipe-IT, and only the columns of the lists
  reach the browser (license keys, phone numbers, addresses and prices do not). The Snipe-IT token never leaves the
  server. A token of a Snipe-IT account that may only view is recommended.

## [1.14.5] - 2026-10-21

### Changed

- Audit Log: the choices of the Action filter now have readable names ("Sign-in failed", "Account status changed", "API
  token revoked"), grouped by part of the app (Sign-in, Accounts, Radio, Documents, API, …), instead of codes such as
  `auth.login_failed`. The code is still used in the page address (`?action=auth.login_failed`).

## [1.14.4] - 2026-10-21

### Fixed

- Dashboard: the top bar now reads "Dashboard", with "Welcome to Radio Management System" below it (1.14.3 had them the
  other way round). The Dashboard breadcrumb, which only held itself, is not shown.

## [1.14.3] - 2026-10-21

### Changed

- The page title is now in the top bar (beside the sidebar button), and the breadcrumb moves below the top bar, above
  the content of the page ("Dashboard › Register Radio"). The large title above the card is gone, so the title does not
  show twice. On phones, which do not show the top bar, the title stays below the breadcrumb. On the Dashboard the title
  is still "Welcome to Radio Management System".
- Dashboard: both columns of the top row now end level; the Next free Radio IDs panel stretches down to the bottom of
  the Needs action panel.

## [1.14.2] - 2026-10-21

### Changed

- Dashboard: the **Active radios per user company** and **Active radios per type** panels now have the same height; the
  bars per type fill the height of their panel (at least 220 px, also on phones), on one level baseline.

## [1.14.1] - 2026-10-21

### Changed

- Dashboard:
  - **Active radios per user company** is now a donut with the total of radios in the middle, beside its list (count
    and percentage): the five largest companies each have their own colour, radios without a company are light grey,
    and the other companies are grouped as "Other" (their names are written below it);
  - **Active radios per type** is now a column chart: the number above each bar, the name of the type below it;
  - the **Condition of active radios** pie has no percentages inside its slices (they stay in the list).

## [1.14.0] - 2026-10-21

### Added

- The Notifications page follows the design handoff (NotificationsPage), without a subtitle:
  - tabs All, Unread, Radio, Documents and System, each with its count (Radio: the weekly summary of the register;
    Documents: requests to change ICT Masterlist cells and their results; System: API tokens about to expire);
  - **Mark as read** on every unread row, without opening the notification (it also works without JavaScript);
  - its category beside the title; the time reads "12 minutes ago" for today, "Yesterday, 16:40", and "17 Oct, 08:00"
    for older ones (the full date and time in a tooltip).

### Changed

- The Dashboard title is now "Welcome to Radio Management System" (in Indonesian: "Selamat datang di Radio Management
  System").

## [1.13.3] - 2026-10-21

### Changed

- The small line below the page title is removed on every page (Register Radio, Needs Attention, Service & Repair, Radio
  History, Master Data, E-Sign Master, COF Scan, ICT Masterlist, Edit Approvals, Users, Role & Permission, Audit Log,
  API Tokens, Notifications): pages now only have a title, like the Dashboard.
- Register Radio:
  - the Reset button is replaced by a refresh symbol button (Refresh), which reloads the list without clearing the
    search and the filters; filters can still be cleared with "Clear filters" in the Filter panel;
  - the vertical line beside the column of buttons at the right end of the table is removed.

## [1.13.2] - 2026-10-21

### Changed

- The page background (behind the cards, down to the footer) is now white instead of light grey; the sidebar stays grey
  as in production, so the two stand apart. The login page, Stock Opname on phones and the print form are unchanged.

## [1.13.1] - 2026-10-21

### Changed

- The language switch is now **ID | EN** in the top bar, left of the notification bell, so it shows without opening the
  account menu (in 1.13.0 it was inside the account menu). On phones it stays in the sidebar, and on the login page it
  stays "ID | EN" below the form.

## [1.13.0] - 2026-10-21

### Added

- **Indonesian / English.** Switch the language from the account menu (top right), in the sidebar on phones, or with
  "ID | EN" on the login page. Every page follows: menus, titles, buttons, tables, dialogs, confirmations, server and
  validation messages, error pages, month names. The default is Indonesian; the choice is saved per account (and in the
  browser), so it follows you to other devices. New notifications are sent in the language of their recipient.
- Data stays as it is stored, in Indonesian: radio history, notes, the Audit Log, SharePoint content, Excel files, the
  printed checkout form and the labels in the REST API. Locations, conditions, archive reasons, accessories and kinds
  of history are only shown with their names in the language of the page.
- The pages that used to be in English only (Users, Role & Permission, Audit Log, API Tokens) are now in Indonesian too,
  and the parts that used to be mixed (the upload/download loader, some labels) follow the language of the page.

### Changed

- REST API (`/api/v1`): its messages are now in English, also those that used to be in Indonesian (rule refusals,
  validation, SharePoint); clients that send `Accept-Language: id` get Indonesian. The data (location and condition
  labels, check results) is unchanged.
- Sidebar menu names in Indonesian: "Settings" becomes "Pengaturan", "User Registered" becomes "User Terdaftar", "Role &
  Permission" becomes "Role & Hak Akses".

## [1.12.3] - 2026-10-21

### Changed

- The role dialog in Role & Permission (Add Role, Edit and view) now has production's sizes: 720 px wide in the middle
  of the screen, a 16 px title, .7rem field names, 29 px fields with .65rem text, a menu table with 10 px column
  headers and 27 px rows (the Access to Delete columns 90 px each, so a long menu name wraps to a second line as in
  production), 26 px buttons. The colours and shapes of the new design stay.

## [1.12.2] - 2026-10-21

### Changed

- The sidebar background is now a very light grey (`#f8fafc`), as in production, instead of white. This applies to the
  wide sidebar, the icon sidebar and the menu on phones; the ring around the marker dots on the icon sidebar follows
  the background colour.

## [1.12.1] - 2026-10-21

### Changed

- Dashboard:
  - the panels of the top row (Condition of active radios and Next free Radio IDs on the left, Needs action on the
    right) are now as wide as the panels of the bottom row (per company and per type), two equal columns, so the edges
    of the panels line up;
  - at 1366 px the condition pie chart now fits beside its list;
  - the text "Register radio HT & rig · data per …" below the Radio Dashboard title is removed.

## [1.12.0] - 2026-10-21

### Added

- A breadcrumb in the top bar, in place of the page title, for example "Dashboard › Radio Master Data › Radio Types" or
  "Dashboard › Register Radio › Radio 1053":
  - made automatically from the sidebar menu of the page, so every page has one without setting it up page by page;
  - "Dashboard" and the menu above a details page can be clicked to go back; the names of groups (Radio Master Data,
    User Management) are plain text;
  - "Dashboard" is not shown to roles without access to the Dashboard;
  - pages outside the menu (Notifications) show as "Dashboard › Notifications".

  On phones the top bar is not shown; the radio details page keeps its way back to Register Radio above its content.

## [1.11.2] - 2026-10-21

### Changed

- Dashboard: the condition of active radios is shown as a pie chart beside its list (below the list when the card is
  narrow), replacing the stacked bar.
  - Percentages are written on slices that are large enough.
  - A slice can be clicked and goes to Register Radio filtered by its condition, like its row.
  - When the pointer is over a slice or its row, both light up and the others dim.
  - A very small condition (for example 2 of 1,011 radios) still shows as a thin slice; the exact number is in the
    list.

## [1.11.1] - 2026-10-21

### Changed

- The login page shows the Merdeka Tsingshan Indonesia logo between the Merdeka Copper Gold and Merdeka Battery
  Materials logos: 28 px high on wide screens, 24 px on 1366 px screens so that the three keep apart.

## [1.11.0] - 2026-10-21

### Added

- The version number in the footer can be clicked and opens the **Version history** dialog: what changed in each
  version, newest first, with "This version" on the running version. Its content comes from CHANGELOG.md, without the
  Deploy lines (notes for the server), and every signed-in account can open it.

### Fixed

- Dialogs whose content scrolls (Version history, the signature use history) are no longer cut off at the bottom of the
  screen.

## [1.10.13] - 2026-10-21

### Changed

- The sidebar menu is laid out as in production:
  - a 240 px sidebar (248 before); menu rows 36 px high every 40 px, starting 18 px below the logo; 20 px icons and
    13 px menu names in the same position as in production;
  - section titles (RADIO, DOCUMENTS, USER, SETTINGS) 10 px and slightly darker, 18 px below the row above them;
  - submenus (for example the content of Radio Master Data) 0.8rem with production's spacing and indent;
  - the hidden sidebar (a row of icons): 22 px icons every 48 px, and the two logo marks side by side in its middle.

  Its colours (white background, light blue active menu) stay from the new design.

## [1.10.12] - 2026-10-21

### Changed

- Role & Permission: Super Admin can edit the Super Admin role itself again (name, home page, menus and their
  permissions), as in production. That row still has no Delete button, because the Super Admin role cannot be deleted.
  Role managers who are not Super Admin still neither see nor can change this role.

## [1.10.11] - 2026-10-21

### Changed

- The "Register a signature" / "Edit signature" dialog in E-Sign Master is sized as in production: 720 px wide in the
  middle of the screen, a 16 px title, 29 px high fields, 28 px Cancel and Save buttons with their icons. The signature
  box has its frame again, and the sentence below the title is gone, as in production.
- The * of a required field is 4 px from its name, as in production. There used to be an extra space, which made the
  gap almost twice as wide.

## [1.10.10] - 2026-10-21

### Changed

- The summary line below a table has a space on both sides of the dash: "Showing 1 – 1 of 1", "Showing 1 – 25 of 247
  radios", also on the Notifications page. The date range in the Audit Log follows: "08 – 12 Oct 2026".

## [1.10.9] - 2026-10-21

### Changed

- No link is underlined any more when the pointer is over it, on any page. For example the Condition of active radios
  rows on the Dashboard, "Show", "Open inbox", the breadcrumb, and "Select all" / "Clear". Text links turn slightly
  darker instead.

## [1.10.8] - 2026-10-21

### Changed

- The logo at the top of the sidebar as in production:
  - open sidebar: the Merdeka Copper Gold and Merdeka Battery Materials logos 32 px high (28 px before), side by side in
    the middle;
  - hidden sidebar (a row of icons): two small marks side by side, Merdeka Copper Gold 20 px and Merdeka Battery
    Materials 24 px. Before, only the Merdeka Battery Materials mark.

## [1.10.7] - 2026-10-21

### Changed

- Login page: its title is "MTI Radio Monitoring", 24 px at every screen width as in production. This title is short
  enough, so the 23 px size for screens below 1400 px from 1.10.6 is no longer needed.

## [1.10.6] - 2026-10-21

### Changed

- Login page: the font, text sizes, logos and spacing of the sign-in panel are the same as in production (measured
  element by element there):
  - the system font, as in production (production's login page does not load Inter);
  - the Merdeka Copper Gold logo 38 px high and the stacked Merdeka Battery Materials logo 56 px high;
  - the Microsoft button 120 × 58 px with a 24 px logo image;
  - a 24 px title (23 px on screens below 1400 px, so that it stays on one line), text of 15, 14 and 12 px;
  - fields and the Login button 36 px high.

  The colours and shapes of the buttons keep the new style.

## [1.10.5] - 2026-10-21

### Changed

- Login page: the colour of the "Radio Management System" title is the same as in production, Tabler's grey
  `text-secondary` (#667382).

## [1.10.4] - 2026-10-21

### Changed

- Login page: its card now has production's size (1.9.x) at every screen width, replacing the fixed size of 1.10.3. As
  with the old Bootstrap grid, it is 1,070.66 px wide from 1400 px screens, 920.66 px from 1200 px, and 928 px below
  that. The photo takes 7/12, the form 5/12, and it is 687.98 px high inside a 1 px border. Its title is slightly
  smaller so that it fits on one line on wide screens.

## [1.10.3] - 2026-10-21

### Changed

- Login page:
  - its card is back to its size before the new look: the photo 538.88 × 687.98 px and the sign-in panel 382.77 ×
    687.98 px. In narrower windows both shrink in the same proportion;
  - its title is "Radio Management System". The title of the browser tab and the other pages keep the name of the app.

## [1.10.2] - 2026-10-21

### Changed

- Login page: the four marker lines below the photo are removed. The photos still take turns as before.

## [1.10.1] - 2026-10-21

### Fixed

- Links shown as a card, row, badge or icon button were underlined when the pointer was over them (Tabler's `a:hover`
  rule): the number cards on the Dashboard, the notification rows and the link at the bottom of the bell, the version
  badge in the footer, icon buttons in Register Radio and Stock Opname, and the Microsoft button on the login page.
  Text links stay underlined.

## [1.10.0] - 2026-10-21

A new look (design refresh) for every page, and new abilities: Needs Attention, Service & Repair, Stock Opname, handover
with a signature, the Radio ID picker, and editing a row directly in the ICT Masterlist. Built phase by phase on the
`design-refresh` branch; its plan and decisions are in [docs/PLAN.md](docs/PLAN.md).

### Added

- Phase 6, new abilities:
  - a Radio ID picker in the radio form: as soon as an ID is typed, the form says whether it can be used. If not, the
    form gives the reason (the MDC Code contains an F, another radio uses it, or a radio without an ID holds it) with
    its holder, and offers a replacement ID ("Use 768"). Its panel shows the IDs around it (free / used / has an F) and
    the next free ID. New endpoint: `GET radio-management/radio/id-check`.
  - **Handover** on the radio details (with the Update permission):
    - a 900px dialog: the recipient (looked up in the employee directory or typed), BA No. (required), PO No.,
      accessories, condition, the location afterwards, the recipient's department/company, and a signature pad for the
      recipient. The confirm button only becomes active once there is a signature;
    - the radio becomes the recipient's, with one "Handover" history row (the BA as document number) and the audit
      entry `radio.handed_over`;
    - the signature is only for printing: the checkout form opens with that signature in the Responsible User box, and
      it is stored nowhere (not in the audit either);
    - new endpoints: `PUT radio-management/radio/{radio}/handover` and `GET radio-management/radio/employees`.
  - a new **Needs Attention** menu, right below Register Radio, with a red count of open items; the Dashboard has an
    **Open inbox** link. The results of the "Check" column become items to work on:
    - one item per radio, or one per SN / Radio ID that several radios use (the radios are compared, and the columns
      that differ are marked);
    - fixes per kind of check through RadioService (history and audit recorded): correct the SN, archive a duplicate,
      the next free ID, the last ID, choose a type, fill in the BA No. (and archive as lost), record programming or
      verification today, mark as reviewed. Fixing also needs the Update permission on Register Radio;
    - **Dismiss** needs a reason; the item comes back when the data of its radio changes;
    - items can be assigned to accounts that may change radio data; filter All / Assigned to me / Unassigned;
    - audit entries `attention.assigned` and `attention.dismissed`;
    - new endpoints in `radio-management/attention`: `list`, `item`, `PUT assign`, `PUT dismiss`, `PUT fix`.
  - a new **Stock Opname** menu, below Service & Repair: a page for phones, without the sidebar:
    - choose a department (and a company when needed), see the number of radios expected and the last stock take, and
      start a session (a session still open there is continued);
    - scan: the camera reads the SN barcode when the browser can (Chrome on Android, `BarcodeDetector`); otherwise the
      Radio ID or SN is typed or sent by a handheld scanner;
    - check a radio: who holds it (as in the register, someone else, nobody), its condition, notes; every scan is saved
      at once. **Report a problem** opens a service ticket;
    - the summary: verified, not found, other holder, other department. **Send to the register** verifies every radio
      found today (with a history row), records the conditions that changed, and records the audit entry
      `radio.opname_submitted`;
    - an Excel import that deletes the old data is refused while a stock take session is open;
    - new endpoints in `radio-management/opname`: `outlook`, `POST /`, `{opname}`, `{opname}/lookup`,
      `PUT {opname}/scan`, `PUT {opname}/report`, `PUT {opname}/submit`.
  - **Edit row** directly in the ICT Masterlist (K13h): personal accounts (not API clients) that may both request and
    approve changes (in practice the Document Controller and Super Admin) get a pencil on the row number. Its dialog
    holds every column of the sheet; formula cells, cells with a pending request and the File scan column are
    read-only:
    - the workbook is downloaded once, every cell and the columns that identify the row are checked, then it is
      uploaded once with its version (If-Match). Nothing is written when the row has changed (409), someone else saved
      the file (412), or it is locked because it is open in Excel (423);
    - every cell is recorded as a request applied by its author, marked "direct" (the Edit Approvals page writes
      "Written directly by ..."), and in the Audit log as `document.edited_directly`;
    - the four-eyes rule does not apply to them; SharePoint records the app as the one that saved;
    - new endpoint: `PATCH documents/ict-masterlist/row`; the `rows` answer carries `direct`.
  - a new **Service & Repair** menu, below Needs Attention (the entries below it move down one place). Whoever holds
    Register Radio gets the same permissions on this menu: Admin Radio and Super Admin in full, Viewer Radio read:
    - service tickets numbered `SRV-0001` for radios with a problem, one open ticket per radio, not for archived
      radios;
    - its steps are Reported → Sent for service → In repair → Ready for pickup → Returned. When it is sent, the radio's
      location becomes In service; when it returns, its location and condition are chosen. Both write a "Service"
      history row (the SRV number in its description, the BA No. as document number);
    - the page: four numbers (open tickets, average service time over the last 90 days, late by more than 14 days, cost
      this quarter), Board / List, search, a service centre filter, Export Excel (also of finished tickets), and a ticket
      drawer with its steps, facts, activity and notes;
    - audit entries `service.created`, `service.status_changed`, `service.note_added`;
    - an Excel import that deletes the old data is refused while a service ticket is open;
    - new endpoints in `radio-management/service`: `list`, `radios`, `export`, `{ticket}`, `POST /`,
      `PUT {ticket}/status`, `PUT {ticket}/note`.
  - **COF Scan** (when the ICT Masterlist sheet may be opened): three cells above the list (Last synced, Linked to a row,
    Not linked), the warning "N files do not match a row in the ICT Masterlist" with a **Review** button, and an All /
    Linked / Not linked filter with its counts. The counts are for the whole folder, whatever is searched. The
    `documents/cof-scan/list` endpoint accepts `status` (`linked`, `unmatched`) and answers `register.linked`,
    `register.unmatched`, `folder.synced` and `folder.status`.
  - **Audit log**:
    - an **Entity** filter (the kinds of record in the audit, by name: Account, Radio, Radio type, …) and a **Date
      range**: Today, Yesterday, Last 7 days, Last 30 days, All time, or **Custom range…** with a range calendar (click
      the first and the last day, then Apply);
    - the page address keeps the choice (`?entity=Radio&range=7d`, or `?from=…&to=…`);
    - Event details shows the **Browser** ("Chrome 141 on Windows"), read from the stored user agent;
    - `audit-log/list` accepts `entity`, `from` and `to`.

### Changed

- Phase 1, the page frame:
  - a new style layer `public/assets/css/radio-ui.css` (the colours of the design, shadows, motion, component recipes),
    loaded after `custom.css` on every page and on the login page;
  - a 248px sidebar with section labels, groups that fold with an animation, and the count of pending requests on
    **Edit Approvals**. A menu stays marked on the pages below it (the details of a radio mark **Register Radio**);
  - an 80px icon rail with a small logo, lines between sections, marker dots for counts, and the menu name beside the
    icon. The chosen width is remembered and set before the page is drawn, so it does not flicker;
  - a 56px top bar: the menu button, the page title, the company name, a bell with a red count, and the name and role
    of the user, which open the **Notifications** and **Sign out** menu;
  - the main colour is blue `#2563eb`, the page background `#f8fafc`, and the footer always sits at the bottom of the
    window;
  - the login page uses the Inter font (it used to link to font files that did not exist); unused Google Fonts links
    are removed.
- Phase 2, Register Radio, the radio details and Radio History:
  - a new page pattern: a title and a subtitle above one card. The card holds the toolbar, the filters, an **Applied**
    chip for each active filter, the bulk bar, and a ruled table with a **Showing… / Rows per page** footer and page
    numbers. Every list page uses this footer;
  - Register:
    - search as you type;
    - the Active / Archived / All scope as a segmented control, and the Archived scope has columns of its own (last
      user, archive date, reason);
    - clicking a row opens the details of the radio;
    - the action buttons of a row stay visible when the table scrolls sideways;
  - buttons a role may not use are no longer shown: a Viewer does not see Add, Edit, Archive and Delete. The server
    still checks;
  - new dialogs:
    - archive, with the reasons to choose from as cards;
    - restore, bulk verify and bulk archive;
    - delete, which now asks for the Radio ID to be typed;
    - a 760px add/edit radio form, with Location as a segmented control and the free IDs as chips;
  - after saving, archiving or restoring a radio, the result shows as a toast at the bottom of the screen; the results
    of bulk actions stay popups;
  - radio details: a breadcrumb, a header card with a **Print the form** button, a Check results card, three info
    cards, a history timeline with old → new changes, and the Original Excel data;
  - Radio History: a filter row with a choice of kinds (several can be ticked, with their counts) and a calendar for
    the dates. The list follows the filters at once.

- Phase 3, Dashboard, Radio Master Data, E-Sign, Import Excel, COF Scan, ICT Masterlist, Edit Approvals:
  - Dashboard:
    - six new KPIs (coloured icon tiles, large numbers), still links to the filtered list;
    - a Condition card with a stacked bar and a legend that can be clicked, then the next free Radio IDs as chips;
    - a **Needs action** list with **Show** links, bars per company and per type, and a table of recent activity with
      action badges;
  - Radio Master Data: underlined tabs per kind of master data, with the Add button on the right. The add/edit form is
    in a card beside the table, no longer a modal. Search as you type, and the result of saving/deleting as a toast;
  - E-Sign: a Grid view (cards with initials, a preview of the signature, the date it was updated) or a List; the choice
    is remembered;
  - Import Excel:
    - two cards, upload and rules;
    - a new file control and checkboxes, red for "delete the old data";
    - an orange confirmation when the old data will be deleted;
    - the result of the import as number tiles;
  - COF Scan and ICT Masterlist: a title and a card, a toolbar with a search field with an icon, and a ruled table. The
    ICT Masterlist also has:
    - an info banner;
    - File scan status cards with coloured numbers, the chosen one with a blue ring;
    - underlined sheet tabs with their row counts;
  - Edit Approvals:
    - the statuses as underlined tabs with their counts. Processing, Conflict and Failed only show when they hold
      requests;
    - old values in red and struck through, new values in green;
    - the Approve/Reject dialog shows the current value and the new value side by side;
    - successful results show as a toast;
    - the status colours follow the design: Pending yellow, Rejected red;
  - the old Tabler badges and info/warning boxes on other pages also use the colours of the design.

- Phase 4, Audit Log, API Tokens, Users, Role & Permission, Notifications, Login:
  - Audit Log:
    - the Table / Log activity choice as a segmented control;
    - actions as coloured badges (for example Created green, Updated orange, Deleted red), the key still in a tooltip;
    - clicking a row (or a Log activity entry) opens an **Event details** card beside the table: the time to the
      second, the user, the action key, the IP, and the changes line by line. Below 1440px the card moves below the
      table;
    - Log activity: a coloured icon circle per action, a "who did what" sentence, a box of changes, a time line;
  - API Tokens: one card with Tokens and API clients sections. Tokens that are no longer valid are faded. New dialogs to
    create a token (the token shows once, with a curl example) and a client. Revoke and delete use the dialogs of the
    design, with the result as a toast;
  - Users:
    - the Role (several can be ticked) and Status filters are right in the toolbar and apply as they change; the
      separate Filter card is removed;
    - roles show as coloured badges;
    - Last sign-in reads "2 hours ago", with the full date in a tooltip;
    - the row buttons follow the permissions of the role (those not allowed are not shown);
    - a 760px user form with the labels in a 190px column and the sign-in method first;
    - the "Is the data correct?" confirmation as a dialog in the middle;
    - saving, activating/deactivating, passwords and deleting are reported with a toast. No more "Canceled!" popup;
  - Role & Permission:
    - search as you type;
    - a badge with the number of users;
    - View on every role, no Edit and Delete on Super Admin;
    - an 800px dialog with a permission table whose header stays in place while scrolling;
  - Notifications:
    - a 400px bell panel: a coloured icon circle per kind, a blue dot on unread ones;
    - the page at most 960px wide, grouped Today / Yesterday / Earlier;
    - **Mark all as read** on the page works without reloading;
  - Login:
    - a 1180px card with four photos that change slowly (a 24-second cross-fade), replacing the carousel;
    - a 48px Microsoft button and a Login button with a busy state;
    - a 440px login guide.

- Phase 5, motion:
  - pages fade in; dialogs and popups pop in and leave faster, their backdrop fades;
  - menus (user, bell, choices, rows per page) drop down from the side they are opened from;
  - toasts rise from the bottom and go down again;
  - table rows fade in each time the filter, the page or the view changes;
  - the bulk bar, filter chips and the filter panel slide down when they appear; a ticked box "pops";
  - Dashboard: the KPI numbers count up, the condition bar opens from the left, the bars per company/type grow;
  - Log activity: entries rise one by one;
  - the underline of the Edit Approvals tabs and the ICT Masterlist sheets slides to the chosen tab;
  - a newly saved signature card pulses;
  - all motion stops when the operating system asks to "reduce motion" (prefers-reduced-motion): numbers show at once,
    and the login only shows the first photo.

### Fixed

- Role & Permission: in View mode, the permission boxes could be ticked once the role had loaded. They now stay locked.
- Toast messages (e.g. "Ticket SRV-0001 created.") showed white without text: SweetAlert's styles, loaded later,
  overrode their dark background.
- The login page no longer loads pictures that do not exist: the thumbnails of the old carousel, and the `manifest.json`
  icons left over from the template. The manifest link is removed from the login page; the file is still in `public/`.
- `php artisan db:seed` on a database already in use (or `--class=RadioMenuSeeder`) put Approval Management at the same
  position as Audit Log, so their order in the sidebar was undefined. Audit Log and API Tokens now stay below User
  Management, with Approval Management after them.

## [1.9.0] - 2026-10-19

### Added

- A sign-in session has two time limits kept by the server, following OWASP and NIST SP 800-63B-4: **30 minutes without
  activity** and **8 hours since sign-in** even when active all along (`SESSION_IDLE_TIMEOUT`,
  `SESSION_ABSOLUTE_TIMEOUT`). Every page warns with a countdown 2 minutes before the idle limit (**Stay signed in** /
  **Sign out**) and 5 minutes before the 8-hour limit (**Sign in again now** / **Later**).
- An ended session takes the page to the login, which gives the reason; it is recorded in the Audit Log as
  `auth.session_expired`. A form sent from a page whose session has already ended also ends on the login page, not on
  "Page Expired".

### Security

- A password sign-in no longer gives a "remember me" cookie even when the form asks for one, and such cookies from
  before no longer bring a session back.
- The **Microsoft** button always shows the choice of account: a browser still signed in to Microsoft as someone else
  does not sign straight in as that person.

## [1.8.4] - 2026-10-19

### Changed

- Login page: the picture beside the form and the page background are back as before 1.8.3 (the Merdeka Copper Gold
  photos and their slides). The pictures of 1.8.3 are removed from the app.

## [1.8.3] - 2026-10-19

### Changed

- Login page: the picture beside the form is now a painting of the Merdeka Battery Materials processing plant (from the
  company's own aerial photo), and the page background a painting of an industrial valley landscape (made by AI, not a
  real facility). Both replace the Merdeka Copper Gold pictures; the slides of four pictures become one picture, and
  the login page now loads about 0.8 MB of pictures instead of about 3.5 MB. On narrow screens the picture shows as a
  strip above the form.

## [1.8.2] - 2026-10-19

### Changed

- Login page: the text below the form now reads **© Merdeka Group** instead of "© PT Merdeka Copper Gold Tbk", the same
  as the footer of the other pages.

## [1.8.1] - 2026-10-19

### Removed

- The **Welcome!** popup that showed when opening the login page (and other pages). It was a leftover of the template,
  set on every request; popups that do say something (failed sign-in, sign-out) still show.

## [1.8.0] - 2026-10-19

### Added

- Users: a **No** column, and **select many** (a tick column per row and per page, the selection kept when changing
  pages) with an **Activate**, **Deactivate** and **Delete** bar (confirmed by typing the count); its buttons follow the
  permissions of the role. Each account is handled like its single action; your own account and accounts you may not
  manage cannot be ticked. At most 100 accounts at once.

## [1.7.0] - 2026-10-19

### Added

- Register Radio: **select many**. A tick column per radio and per page, a selection that stays when changing pages or
  filters, and **Select all N radios of the filter**. A bar above the table (its buttons follow the permissions of the
  role):
  - **Verify today**: a "Verification" history row for every radio, whose verification date moves forward; it can take
    every result of the filter (stock taking), at most 1,000 at once;
  - **Archive**: one reason, date and note for the ticked radios;
  - **Delete**: for wrong entries, confirmed by typing the count;
  - **Export selected**: an Excel file with only the ticked radios.

  Archive and Delete only take radios ticked one by one (at most 100). Radios that cannot be handled are named with
  their reason.

## [1.6.1] - 2026-10-18

### Changed

- Docker: the `certs/` folder beside `docker-compose.yml` is always mounted into the container (read-only), the place
  for the root CA certificate of the domain controller (`certs/ca.pem`) for LDAP sign-in that checks the certificate.
  Before, that line had to be uncommented by hand in `docker-compose.yml`, which clashed with the next `git pull`.
- README and `.env.example`: how to turn on `LDAP_TLS_VERIFY`: the root CA certificate, and an `LDAP_URL` with the DNS
  name of the AD server (not an IP), because the check matches that name against its certificate.

## [1.6.0] - 2026-10-18

### Added

- **Notifications** in the header bell: the unread count (updated every minute), the 8 newest notifications, mark as
  read, and a **See all notifications** page. They cover:
  - requests to change ICT Masterlist cells, for whoever may approve them; their results (applied, rejected, failed,
    conflict) for whoever requested them;
  - API tokens that expire within 7 days, for their owner (API client tokens: for Super Admin);
  - the weekly "Needs action" summary of the register, for whoever may change Register Radio.
- The Edit Approvals page can be opened already filtered through its address (`?status=pending&search=...`): the
  notification links use it.

## [1.5.4] - 2026-10-18

### Removed

- Dashboard: the **Register Radio** button beside the title. Register Radio is already in the sidebar, and the numbers
  on the dashboard still open its list.

## [1.5.3] - 2026-10-17

### Fixed

- Edit Approvals: an edited workbook now and then could not be saved temporarily on a Windows server (about 1 in 200
  times: its new file was held by another process, for example an antivirus), and the approval ended "Failed" without a
  clear reason. That save is now retried automatically, and the workbook about to be sent to SharePoint is read back
  first to make sure its changes are there. If it still fails, the message says that nothing was written to SharePoint
  and that the request can be approved again.

## [1.5.2] - 2026-10-17

### Fixed

- Add User: names with brackets, such as display names in Active Directory ("Budi Santoso [MTI]"), were refused on
  Submit ("The name may only have letters, ..."). Since 1.5.0 Find employee for LDAP fills in the name from Active
  Directory, so almost everyone found there could not be saved. Names may now contain `( ) [ ] { }`; other marks such
  as `;`, quotes and `< >` are still refused.

## [1.5.1] - 2026-10-17

### Removed

- Login: the "Merdeka Copper Gold Group, @merdekacoppergold.com, access via Google SSO" card in the **Access Login
  Guide**. This app has no Google sign-in: the template's Google sign-in (a button hidden while not set up, the
  `auth/google/*` addresses) was never set up and is removed too. Sign-in stays through Microsoft or a Merdeka account
  (password / Active Directory).

### Changed

- Login: the Microsoft card in the Access Login Guide only names `@merdekabattery.com` (without `@scmnickel.com`).

## [1.5.0] - 2026-10-17

### Added

- The version number of the app: the `VERSION` file, shown in the footer of every page, in the API documentation
  (`/docs/api`) and in `php artisan about`; this changelog; a git tag `vX.Y.Z` for every version.
- Add User: with the **LDAP** method, Find employee searches **Active Directory** by name, username or e-mail. Choosing
  someone fills in Name, Email and Directory username exactly as in the directory. Disabled accounts are not offered.
- The Directory username is checked against Active Directory when the account is saved: a username that does not exist,
  or a disabled account, is refused. While Active Directory cannot be reached, LDAP accounts cannot be saved.

### Changed

- Add User: **Sign-in method** is now at the top, because the method decides where Find employee searches. With
  Microsoft, Find employee still searches the HR database.

## [1.4.0] - 2026-10-17

### Added

- API phase 3:
  - COF Scan: the list of files in the scan folder with their description from the COF sheet, and the download of one
    file (PDF or picture);
  - ICT Masterlist: the workbook, sheets and rows (search, sort, pages; on the COF sheet also the "File scan" column and
    its counts);
  - requests to change a cell, the list of requests, approving and rejecting: only with a personal token, never with an
    API client token, and nobody approves their own request;
  - E-Sign: the list of signatures and their use (without the pictures), and deactivating a signature.
- Limits: reading from SharePoint 30 requests per minute per token, approving requests 10 per minute.

## [1.3.0] - 2026-10-17

### Added

- API phase 2:
  - an Excel export of the register, with the same filters as its list;
  - the content of the Print form as data, without signatures;
  - Import Excel: the last report and importing a workbook (`fresh=1` replaces the whole register and needs the Delete
    permission on Register Radio);
  - users, roles and the audit log, read-only.
- Import and export through the API: 10 requests per minute per token.

## [1.2.0] - 2026-10-17

### Added

- A REST API `/api/v1` for other applications, with its documentation at `/docs/api`.
- The **Settings → API Tokens** page: tokens for an API client (the account of an application) or personal tokens;
  read-only or read and write; valid for 30 to 365 days; shown only once.
- API phase 1: me, references, dashboard, radios (list, details, add, change, archive, restore, history, delete, free
  IDs, ID and SN check), the history of all radios, and the four master data tables.
- Every endpoint follows the menu permissions of its page in Role & Permission; 60 requests per minute per token;
  changes made through the API are recorded in the Audit Log with the token that made them.

## [1.1.1] - 2026-10-17

### Fixed

- The Audit Log names the home page of a role by the name of its page, not by its menu number.

## [1.1.0] - 2026-10-17

### Added

- The **Audit Log** menu (Settings, Super Admin only): who signed in (also those refused, with the reason) and out, and
  who changed what (accounts, roles, radios, master data, E-Sign, Excel imports, ICT Masterlist change requests), with
  the values before and after. Shown as a table or a timeline, with user and action filters. Passwords, tokens and
  signature pictures are never recorded.

### Security

- The request logs (`activity_logs`, `error_logs`) no longer store E-Sign signature pictures.

## [1.0.0] - 2026-10-17

The first version running in production. It holds:

- **Radio**: Register Radio (one row for one physical radio by SN; Radio ID and MDC code, and a Radio ID whose MDC code
  contains an F cannot be used; location and condition; archive; the "Needs checking" mark; the Owner MTI / Non MTI
  column), Radio History, Radio Master Data, Excel import and export.
- **Print**: the IT Equipment Checkout Form for one radio, with an on-screen signature for RESPONSIBLE USER and the IT
  signature from **E-Sign Master** for PROCESSED BY.
- **Documents** (SharePoint): COF Scan (the content of the scan folder), ICT Masterlist (the workbook as tables), each
  checked against the other, and changes to ICT Masterlist cells through requests and **Edit Approvals**.
- **User**: Users (status, sign-in method, last sign-in, deactivate, set password; Add User with Find employee from the
  HR database, the LDAP / Microsoft sign-in method and Directory username) and Role & Permission. Sign in with Microsoft
  or with an Active Directory password.
- **Docker**: `Dockerfile` and `docker-compose.yml` (port 26102), ready behind an HTTPS reverse proxy.
