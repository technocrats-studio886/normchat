# UI/UX DESIGN BRIEF

## Company: Sysnavia

## Product Line: Uplift

## Product Name: Normchat

## Platform: Mobile Web App & Progressive Web App (PWA)

---

# 1. DESIGN OBJECTIVE

Tujuan utama UI/UX Normchat adalah menciptakan pengalaman chat grup yang:

* Seamless
* Cepat
* Minim friction
* Terasa modern dan ringan
* Aman secara psikologis dan teknis
* Konsisten di mobile web dan PWA

Produk harus terasa seperti aplikasi chat modern, bukan dashboard atau enterprise system yang berat.

User harus bisa:

* Masuk ke grup dengan cepat
* Chat tanpa hambatan
* Menambahkan AI dengan mudah
* Mengelola grup tanpa kebingungan
* Mengekspor chat dengan jelas

---

# 2. CORE UX PRINCIPLES

## 1. Mobile-first

Semua desain dimulai dari layar mobile terlebih dahulu.

Karakteristik:

* Thumb-friendly layout
* Bottom navigation
* Touch-first interaction
* Gesture-ready

---

## 2. Low Friction Interaction

Setiap aksi harus sesingkat mungkin.

Target:

* Join group < 10 detik
* Send message < 1 detik
* Add member < 5 detik

---

## 3. Context Always Visible

User tidak boleh kehilangan konteks.

Harus selalu terlihat:

* Nama grup
* Status AI
* Jumlah member
* Connection state

---

## 4. Predictable Interaction

Semua aksi harus konsisten.

Contoh:

* Swipe = action
* Tap = select
* Long press = options

---

## 5. AI Should Feel Natural

AI tidak boleh terasa seperti fitur tambahan.

AI harus terasa seperti:

* Participant biasa
* Responsif saat di-tag
* Tidak mengganggu percakapan

---

# 3. TARGET USER

## Primary User

* Team kecil
* Community manager
* Project group
* Collaboration group

---

## Secondary User

* Startup team
* Study group
* Developer team
* Content creator group

---

# 4. USER JOURNEY

## Journey 1 — First Entry

Flow:

Use Now
→ Choose SSO
→ Login
→ Enter group
→ Start chatting

Goal:

User masuk ke chat secepat mungkin.

---

## Journey 2 — Join Group

Flow:

Open invite link
→ Enter password
→ Waiting approval (optional)
→ Enter group

Goal:

User memahami bahwa grup aman.

---

## Journey 3 — Add AI

Flow:

Open group settings
→ Tap Add AI
→ Select provider
→ Authenticate
→ AI joins group

Goal:

Menambahkan AI terasa mudah dan jelas.

---

## Journey 4 — Chat With AI

Flow:

Type message
→ Tag AI
→ Send message
→ AI reply

Goal:

Interaksi AI terasa natural.

---

## Journey 5 — Export Chat

Flow:

Open group menu
→ Tap Export
→ Select format
→ Download file

Goal:

Export terasa profesional dan terpercaya.

---

# 5. INFORMATION ARCHITECTURE

## Level 1 Navigation

* Groups
* Chat
* Settings
* Profile

---

## Level 2 Navigation

### Groups

* Group list
* Create group
* Join group

### Chat

* Message list
* Message input
* Attachment

### Settings

* Group settings
* Member management
* AI management
* Export
* Backup

### Profile

* Account info
* Subscription
* Logout

---

# 6. SCREEN LIST

## Authentication Screens

* Landing page
* SSO selection
* Login loading state

---

## Group Screens

* Group list
* Create group
* Join group
* Password entry
* Approval waiting

---

## Chat Screens

* Chat main screen
* Message thread
* Mention suggestion
* AI response state

---

## Settings Screens

* Group settings
* Member list
* Add member
* Add admin
* Add AI
* Export screen
* Backup screen

---

## System Screens

* Loading
* Empty state
* Error state
* Success state

---

# 7. CHAT SCREEN DESIGN STRUCTURE

## Header

Contains:

* Group name
* Member count
* AI indicator
* Settings button

---

## Message Area

Contains:

* Message bubble
* Timestamp
* Sender name
* AI label

---

## Input Area

Contains:

* Text input
* Mention trigger
* Send button

---

# 8. INTERACTION DESIGN

## Tap

Used for:

* Open chat
* Send message
* Select menu

---

## Long Press

Used for:

* Message options
* Copy message
* Delete message

---

## Swipe

Used for:

* Reply message
* Open quick actions

---

# 9. VISUAL DESIGN PRINCIPLES

## Style Direction

* Modern
* Clean
* Minimal
* Professional
* Mobile-first

---

## Color System

Primary:

* Brand color

Secondary:

* Neutral gray

Status Colors:

* Success
* Warning
* Error

---

## Typography

Primary Font:

* Sans-serif modern font

Hierarchy:

* Heading
* Body
* Caption

---

# 10. COMPONENT LIST

## Core Components

* Button
* Input
* Card
* Modal
* Toast
* Dropdown
* Bottom sheet

---

## Chat Components

* Message bubble
* AI badge
* Mention suggestion
* Typing indicator
* Message timestamp

---

## System Components

* Loader
* Skeleton
* Empty state
* Error message

---

# 11. UX STATES

## Loading State

Used when:

* Fetching messages
* Connecting to server

---

## Empty State

Used when:

* No messages
* No groups

---

## Error State

Used when:

* Connection lost
* Permission denied

---

## Success State

Used when:

* Member added
* Export completed

---

# 12. PERFORMANCE REQUIREMENTS

Target:

* First load < 2 seconds
* Message send < 1 second
* Screen transition smooth

---

# 13. ACCESSIBILITY REQUIREMENTS

* Readable font size
* High contrast text
* Large touch target
* Keyboard navigation support

---

# 14. PWA REQUIREMENTS

Must support:

* Installable app
* Offline capability (basic)
* Fast startup
* Mobile responsiveness

---

# END OF DOCUMENT
