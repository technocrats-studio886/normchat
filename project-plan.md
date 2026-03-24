# PROJECT PLAN

## Company: Sysnavia

## Product Line: Uplift

## Product Name: Normchat

## Platform: Mobile Web App (Primary) & Progressive Web App (PWA)

## Stack: Laravel Full-Stack

---

# 1. PRODUCT POSITIONING

Normchat adalah group chat platform untuk manusia dan AI yang dibangun sebagai **mobile web app first**.

Produk ini bukan desktop web app yang dipaksa responsif. Semua keputusan UX, struktur layar, navigasi, dan interaksi dibuat dengan pola mobile web app pada umumnya:

* dibuka dari browser mobile
* terasa seperti aplikasi native mobile
* navigasi sederhana dan ringkas
* nyaman dipakai dengan satu tangan
* fokus pada kecepatan akses dan chat flow
* PWA-ready untuk installable experience

Desktop tetap didukung, tetapi hanya sebagai experience sekunder.

---

# 2. PRODUCT GOALS

## Primary Goals

* Membuat group chat yang lebih nyaman daripada group chat biasa
* Menyediakan access control yang aman dan jelas
* Menjadikan AI sebagai participant yang hanya merespons saat di-tag
* Menyediakan backup, version history, dan recovery
* Menyediakan export chat ke PDF dan DOCX dengan format rapi

## Business Goals

* Subscription only
* Monthly only
* Satu plan utama yang sederhana
* Seat-based pricing setelah seat yang termasuk di plan utama

## Technical Goals

* Stack sederhana
* Maintenance rendah
* Deploy cepat
* Stabil dan robust
* Cocok dijalankan di satu VPS

---

# 3. MOBILE WEB APP PRINCIPLES

Normchat harus mengikuti prinsip umum mobile web app:

## 3.1 Mobile-first Layout

* Prioritas utama adalah layar HP
* Layout dibuat berdasarkan portrait orientation
* Elemen penting harus terlihat tanpa scroll panjang
* Informasi sekunder disembunyikan di menu atau bottom sheet

## 3.2 Thumb-friendly Interaction

* Tombol utama mudah dijangkau ibu jari
* Action yang sering dipakai diletakkan di area bawah
* Hindari tap target kecil
* Hindari gesture yang terlalu kompleks

## 3.3 App-like Experience

* Transisi cepat dan halus
* Navigasi terasa seperti aplikasi native
* State perubahan harus jelas
* Loading, success, dan error harus terasa natural

## 3.4 Minimal Friction

* User tidak perlu berpikir banyak untuk masuk ke grup
* Join flow harus singkat
* Chat flow harus langsung ke inti
* Settings tidak boleh mengganggu pengalaman chatting

## 3.5 Bottom-Oriented Navigation

Struktur navigasi utama harus cocok untuk mobile:

* Home / Groups
* Chat
* Create / Action utama
* Notifications / Activity
* Profile / Settings

## 3.6 PWA Readiness

* Bisa di-install ke home screen
* Mendukung startup cepat
* Basic offline behavior bila memungkinkan
* Icon, splash screen, dan manifest harus siap

---

# 4. ROLE AND ACCESS CONTROL

## Owner

Owner adalah pemilik grup dengan akses penuh.

Hak akses Owner:

* Add / Remove Admin
* Add / Remove AI
* Set / Change group password
* Enable / Disable approval
* Approve member
* Manage billing
* Access backup and recovery
* Export chat
* Transfer ownership
* Delete group
* View audit logs

## Admin

Admin adalah operator grup.

Hak akses Admin:

* Add member
* Remove member
* Manage member list
* Moderate chat
* Pin / unpin message
* Approve member jika diizinkan oleh owner

Admin tidak boleh:

* Change password
* Add AI
* Manage billing
* Recover history
* Transfer ownership

## AI

AI adalah participant khusus dalam grup.

Behavior AI:

* Hanya merespons ketika di-tag
* Tidak mengganggu chat jika tidak dipanggil
* Masuk ke grup hanya jika ditambahkan oleh owner
* Terhubung ke provider AI seperti ChatGPT atau Claude

Hak AI:

* Read relevant context
* Send response when mentioned
* Summarize conversation jika diminta
* Assist chat secara natural

AI tidak boleh:

* Add / Remove member
* Change role
* Change password
* Approve join
* Manage billing
* Modify history

---

# 5. CORE FEATURE SET

## A. Authentication

* Only SSO
* CTA: Use Now
* Continue with SSO
* Login with Claude
* Login with ChatGPT

## B. Group Access

* Create group
* Join via invite link
* Password gate
* Optional approval gate
* Member status: active, pending, blocked, invited

## C. Chat System

* Send message
* Reply message
* Mention AI
* Realtime updates
* Message persistence
* Unread state

## D. AI System

* Add AI by owner only
* Connect AI provider
* AI join as participant
* Mention-triggered response
* Multiple providers support

## E. History and Recovery

* Backup snapshot
* Version history
* Restore previous state
* Recovery log

## F. Export System

* Export chat to DOCX
* Export chat to PDF
* Layout seperti dialog dan naskah cerita

## G. Billing System

* Monthly subscription
* No free plan
* One main plan
* Two seats included
* Extra seat charged per user

---

# 6. TECH STACK

## Frontend and Backend

Framework:

* Laravel

Responsibilities:

* Server-side rendering jika dibutuhkan
* Application logic
* Authentication
* Group management
* RBAC enforcement
* Chat rendering logic
* Export and background jobs

## UI Layer

* Blade
* Alpine.js
* Livewire untuk interaksi yang lebih cepat dan ringan
* Tailwind CSS

## Realtime

* Laravel Reverb atau WebSocket compatible solution
* Laravel Echo jika dibutuhkan pada client side

## Database

* PostgreSQL

## Cache and Queue

* Redis
* Laravel Queue
* Laravel Scheduler

## Storage

* Local storage on VPS

Suggested path:

* /opt/normchat/storage/

Folders:

* exports/
* backups/
* attachments/

## Server

* One VPS
* Nginx
* PHP
* PostgreSQL
* Redis
* Supervisor

---

# 7. MOBILE WEB APP UX REQUIREMENTS

## 7.1 Navigation Pattern

Navigasi utama harus mudah dipakai di layar mobile.

Disarankan:

* Bottom navigation untuk menu utama
* Bottom sheet untuk aksi sekunder
* Modal seperlunya saja
* Hindari sidebar permanen sebagai navigasi utama

## 7.2 Chat-first Layout

Halaman chat adalah inti produk.

Struktur halaman chat:

* Header ringkas
* Area pesan utama
* Input bar tetap mudah dijangkau
* Action penting di bawah

## 7.3 One-handed Usage

Semua interaksi sering dipakai harus bisa dilakukan dengan satu tangan:

* Buka group
* Kirim pesan
* Tag AI
* Buka menu cepat
* Pindah antar group

## 7.4 Density Control

Karena mobile screen sempit:

* Hindari teks panjang di layar utama
* Gunakan card pendek dan ringkas
* Detail disimpan di drill-down screen
* Setting dibagi per section

## 7.5 Fast Perceived Performance

* Skeleton loading
* Optimistic UI bila aman
* Transisi cepat
* Minim blocking action

---

# 8. SYSTEM MODULES

## 8.1 Auth Module

Responsibilities:

* Handle SSO login flow
* Session management
* User provisioning
* Logout

## 8.2 Group Module

Responsibilities:

* Create group
* Update group
* Delete group
* Join group via invitation
* Handle password validation
* Handle approval workflow

## 8.3 Membership Module

Responsibilities:

* Invite member
* Approve / reject member
* Remove member
* Manage member status
* Assign role

## 8.4 RBAC Module

Responsibilities:

* Owner permissions
* Admin permissions
* AI permissions
* Permission checking in backend

## 8.5 Chat Module

Responsibilities:

* Send and receive messages
* Reply support
* Mention support
* Store message history
* Realtime updates

## 8.6 AI Module

Responsibilities:

* Connect AI provider
* Store provider reference securely
* Trigger response when mentioned
* Save AI response as message

## 8.7 History Module

Responsibilities:

* Store message versions
* Generate backup snapshot
* Restore snapshot
* Record recovery event

## 8.8 Export Module

Responsibilities:

* Generate PDF
* Generate DOCX
* Format export as structured conversation
* Store export result on server

## 8.9 Billing Module

Responsibilities:

* Track active subscription
* Calculate seat usage
* Enforce paid access
* Manage monthly billing status

## 8.10 Audit Module

Responsibilities:

* Track security events
* Track admin actions
* Track recovery and export actions
* Provide traceability

---

# 9. DATABASE DESIGN

## users

Fields:

* id
* name
* email
* avatar_url
* auth_provider
* provider_user_id
* created_at
* updated_at

## groups

Fields:

* id
* name
* description
* owner_id
* password_hash
* approval_enabled
* created_at
* updated_at
* deleted_at

## group_members

Fields:

* id
* group_id
* user_id
* role_id
* status
* invited_by
* approved_by
* joined_at
* created_at
* updated_at

## roles

Fields:

* id
* key
* name
* description

Role values:

* owner
* admin
* ai

## permissions

Fields:

* id
* key
* name
* description

Example permissions:

* add_member
* remove_member
* add_ai
* change_password
* set_approval
* export_chat
* recover_history
* manage_billing
* pin_message
* delete_message
* view_audit_log

## role_permissions

Fields:

* id
* role_id
* permission_id

## messages

Fields:

* id
* group_id
* sender_type
* sender_id
* content
* created_at
* updated_at
* deleted_at

## message_versions

Fields:

* id
* message_id
* version_number
* content_snapshot
* edited_by
* edited_at

## approvals

Fields:

* id
* group_id
* user_id
* status
* requested_at
* approved_by
* rejected_by
* note

## ai_connections

Fields:

* id
* group_id
* provider_name
* provider_account_ref
* config_encrypted
* active
* created_by
* created_at

## group_backups

Fields:

* id
* group_id
* backup_type
* storage_path
* created_by
* created_at

## recovery_logs

Fields:

* id
* group_id
* backup_id
* restored_by
* restored_at
* reason

## exports

Fields:

* id
* group_id
* file_name
* storage_path
* file_type
* status
* created_by
* created_at

## subscriptions

Fields:

* id
* group_id
* plan_name
* status
* billing_cycle
* main_price
* included_seats
* created_at
* updated_at

## subscription_seats

Fields:

* id
* subscription_id
* user_id
* seat_type
* active

## audit_logs

Fields:

* id
* group_id
* actor_id
* action
* target_type
* target_id
* metadata_json
* created_at

---

# 10. USE CASES

## Use Case 1: Login

Flow:

Use Now
→ Choose SSO
→ Login
→ Enter dashboard

## Use Case 2: Create Group

Flow:

Owner login
→ Create group
→ Set password
→ Enable or disable approval
→ Start inviting member

## Use Case 3: Join Group

Flow:

Open invite link
→ Enter password
→ If approval active, wait for approval
→ Enter group chat

## Use Case 4: Add AI

Flow:

Owner opens settings
→ Add AI
→ Choose provider
→ Authenticate provider
→ AI joins group

## Use Case 5: Chat with AI

Flow:

User sends message
→ Tag AI
→ Backend triggers provider
→ AI replies into chat

## Use Case 6: Export Chat

Flow:

Owner/admin opens export
→ Select format
→ Generate file
→ Download DOCX or PDF

## Use Case 7: Recover History

Flow:

Owner opens backup list
→ Select snapshot
→ Confirm recovery
→ System restores state

---

# 11. API MODULES

## Auth API

* Login
* Callback
* Logout
* Session check

## Group API

* Create group
* Get group
* Update group
* Delete group

## Membership API

* Invite member
* Approve member
* Reject member
* Remove member

## Message API

* Send message
* Edit message
* Delete message
* List messages

## AI API

* Connect provider
* Remove provider
* Trigger response

## Export API

* Create export job
* Check export status
* Download export file

## Billing API

* Get subscription
* Update subscription
* Check seat usage

## Audit API

* Get logs
* Filter logs

---

# 12. NON-FUNCTIONAL REQUIREMENTS

## Performance

* Fast initial load on mobile
* Smooth message sending
* Export runs in background

## Security

* Password hashing
* Encrypted AI credentials
* Backend permission enforcement
* Audit logging for sensitive actions

## Reliability

* Daily backup
* Queue retry for failed jobs
* Data consistency on recovery

## Scalability

* Support multiple groups
* Support multiple AI providers
* Support many members per group

## Maintenance

* Single codebase
* Single VPS deployment
* Minimal operational complexity

---

# 13. DEPLOYMENT ARCHITECTURE

Frontend and Backend:

* Laravel application on a single VPS

Suggested server components:

* Ubuntu
* Nginx
* PHP
* PostgreSQL
* Redis
* Supervisor

Storage:

* Local VPS storage

Backup strategy:

* Daily automatic backup
* Retention policy for backups
* Optional off-server copy for safety

---

# 14. MILESTONES

## Phase 1: Foundation

Deliverables:

* Laravel project setup
* Database schema
* Auth flow
* Role and permission system
* Base UI shell optimized for mobile

## Phase 2: Core Group Chat

Deliverables:

* Group creation
* Invite flow
* Password gate
* Approval flow
* Realtime chat
* Mobile-first chat layout

## Phase 3: AI Integration

Deliverables:

* AI provider connection
* AI participant logic
* Mention-triggered reply

## Phase 4: History and Export

Deliverables:

* Message version history
* Backup system
* Recovery system
* PDF and DOCX export

## Phase 5: Billing and Launch Readiness

Deliverables:

* Subscription enforcement
* Seat calculation
* Monitoring
* Backup validation
* Production deployment

---

# END OF DOCUMENT
