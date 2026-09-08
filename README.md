# 🧩 GLPI ExportData — Plugin Ecosystem

> A suite of custom GLPI plugins built to extend incident management, reporting, and support automation — including a fully offline AI assistant.

![Status](https://img.shields.io/badge/status-prototype-yellow)
![Platform](https://img.shields.io/badge/GLPI-10.0.22-0b5fa5)
![License](https://img.shields.io/badge/license-GPLv3%2B-informational)
![AI](https://img.shields.io/badge/AI-100%25%20offline-success)

---

## 📖 Overview

**ExportData** is a modular GLPI plugin developed as an exploratory engineering project to demonstrate how far GLPI's plugin architecture can be pushed. It adds five major capabilities on top of native GLPI, without touching a single line of GLPI core code — plus a full customization of the ticket creation workflow and a real-world deployment test of the GLPI Inventory Agent across a virtualized network.

| | |
|---|---|
| **Core platform** | GLPI 10.0.22 |
| **Backend** | PHP 8.2, MySQL / MariaDB |
| **Frontend** | Vanilla JS, Chart.js |
| **AI engine** | Ollama + Llama 3.2 (1B) — fully local |
| **PDF / Excel** | TCPDF, HTML-to-Excel technique |

---

## ✨ Features

### 📤 Multi-Format Data Export
Export any of 11 GLPI data types (computers, software, users, tickets, printers...) into **CSV, JSON, XML, Excel, or PDF** — with a CSRF-protected form and zero external dependencies beyond what GLPI already ships with.

### 📊 Advanced Statistics Dashboard
A configurable analytics dashboard rendering GLPI data (computers by manufacturer, tickets by status, OS distribution...) through **5 chart types** via Chart.js: bar, doughnut, radar, line, and pie — a big step up from GLPI's native single-chart statistics module.

### 🗂️ Custom Report Builder
A drag-free, click-to-order report generator: pick a primary data type, optionally combine it with a related secondary type (e.g. *Computer + Assigned User*), choose fields, reorder them, preview live, and export. Every field is validated against a server-side **whitelist** before hitting the SQL query — no exceptions.

### 🔍 Real-Time Solution Search
An AJAX-powered search box embedded directly in the ticket creation form. As the user types, it queries both the **Knowledge Base** and **resolved tickets** for matching keywords, surfacing existing solutions before a duplicate ticket is even created.

### 🤖 Offline AI Assistant
A conversational assistant powered by **Ollama running Llama 3.2 (1B)** — 100% local, no data ever leaves the server. It handles three distinct intents, detected reliably via PHP keyword matching (not left to the LLM):

| Intent | Behavior |
|---|---|
| **Statistics** | Direct SQL `COUNT()` query — zero AI involvement, zero hallucination risk |
| **Ticket draft** | LLM drafts a title + description; **human confirmation required** before any ticket is created |
| **Solution search** | Retrieval-Augmented Generation (RAG): real KB/ticket excerpts are fed to the model, which is instructed to answer *only* from that context |

### 🎫 Ticket Form Customization
The native ticket form was reshaped for a more realistic business classification:
- `Type`: **Incident** / **Service** (renamed from *Request*)
- `Priority`: simplified from 5 to **3 levels**
- `Impact`: new 3-level field
- `Department`: free-text field, stored in a dedicated linked table and exposed as a native GLPI search/report column
- 6 custom ticket categories configured

### 🌐 Inventory Agent — Distributed Deployment
GLPI Inventory Agent was deployed from a **Windows 10 VM** to a **separate Ubuntu server** (Apache2 + MariaDB), validating end-to-end asset discovery in a realistic client/server topology — including full log-based debugging (Apache access logs, GLPI internal logs) and diagnosis of a system clock desynchronization (fixed via **Chrony**) that was silently blocking inventory updates.

---

## 🏗️ Architecture

```
plugins/exportdata/
├── setup.php              # Plugin metadata, hooks declaration
├── hook.php
├── inc/                   # Menu classes (extend CommonGLPI) + config
│   ├── menu.class.php
│   ├── statsmenu.class.php
│   ├── reportmenu.class.php
│   ├── chatmenu.class.php
│   └── reportfields.php   # Whitelisted fields & relations
├── front/                 # User-facing pages
│   ├── export.php
│   ├── stats.php
│   ├── report.php
│   └── chat.php
├── ajax/
│   └── suggest.php        # Real-time KB/ticket search endpoint
├── css/
│   └── exportdata.css     # Centralized styling for all pages
└── js/
    └── custom_ticket_form.js
```

No GLPI core files are modified — the plugin follows GLPI's standard extension model end-to-end (`CommonGLPI` inheritance, `$PLUGIN_HOOKS`, native `Session::getNewCSRFToken()`).

---

## 🔐 Security Highlights

- **CSRF tokens** on every state-changing form
- **Whitelisted field selection** in the Report Builder — user input never reaches raw SQL unchecked
- **No automatic ticket creation** — the AI assistant always requires explicit human confirmation
- **AI guardrails** — when no relevant context is found, the assistant returns a fixed, honest response instead of calling the LLM at all

---

## ⚠️ Known Limitations

| Limitation | Reason |
|---|---|
| AI assistant only reliably handles GLPI-related questions | Intentional design choice to avoid hallucinated answers outside its knowledge scope |
| Reduced AI response quality outside French/English | Llama 3.2 1B is primarily English-optimized |
| AI responses take a few seconds | No dedicated GPU on the development machine (Intel i5-10310U, 8 GB RAM) |

---

## 🖥️ Development Environment

| Component | Detail |
|---|---|
| CPU | Intel Core i5-10310U (4C/8T), no dedicated GPU |
| RAM | 8 GB |
| Local stack | XAMPP (Apache, PHP 8.2, MariaDB) |
| GLPI version | 10.0.22 |
| Editor | Visual Studio Code |

---

## 📌 Project Status

This is an **exploratory prototype** built during an internship — not a production deployment. It demonstrates GLPI's extensibility and lays the groundwork for a potential future rollout, pending validation by the hosting organization's IT department.

---

## 📄 License

GPLv3+ — consistent with GLPI's own licensing.
