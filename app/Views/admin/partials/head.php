    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= base_url('assets/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/favicon.png') ?>">
    <title><?= esc($title) ?> | KINTA Admin</title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css"
    >
    <?php if ($masterDataModule ?? false): ?>
        <link rel="stylesheet" href="https://cdn.datatables.net/3.1.4/css/dataTables.dataTables.css">
    <?php endif ?>
    <?php if ($masterDataModule ?? false): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css">
    <?php endif ?>
    <style>
        :root {
            --kinta-primary: #087cc1;
            --kinta-primary-hover: #055d93;
            --kinta-primary-dark: #075985;
            --kinta-primary-soft: #e0f2fe;
            --kinta-secondary: #e5e7eb;
            color-scheme: light;
            font-family: system-ui, sans-serif;
            color: #172033;
            background: #f1f5f9;
        }

        * { box-sizing: border-box; }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            font-size: 15px;
            background: #f1f5f9;
        }

        a {
            color: var(--kinta-primary-dark);
            text-decoration: none;
        }

        a:hover,
        a:focus-visible { text-decoration: underline; }

        .sidebar {
            position: fixed;
            z-index: 20;
            top: 0;
            bottom: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            width: 60px;
            overflow-x: hidden;
            overflow-y: auto;
            background: white;
            box-shadow: 2px 0 10px #1112;
            transition: width .18s ease, transform .18s ease;
        }

        .sidebar:hover,
        .sidebar:focus-within,
        .sidebar.is-open { width: 240px; }

        .sidebar-toggle {
            position: fixed;
            z-index: 40;
            top: 16px;
            left: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            margin: 0;
            padding: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: var(--kinta-primary-dark);
            background: white;
            box-shadow: 0 2px 8px #1112;
            cursor: pointer;
            transition: left .18s ease, background-color .15s ease, color .15s ease;
        }

        .sidebar-toggle:hover,
        .sidebar-toggle:focus-visible {
            color: #172033;
            background: var(--kinta-secondary);
        }

        .sidebar-toggle svg {
            width: 20px;
            height: 20px;
        }

        body.sidebar-expanded .sidebar-toggle { left: 222px; }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 18px;
            min-height: 64px;
            padding: 0 19px;
            color: #073b64;
            font-weight: 700;
            white-space: nowrap;
        }

        .brand-icon {
            flex: 0 0 22px;
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .brand-label {
            opacity: 0;
            transition: opacity .12s ease;
        }

        .admin-nav {
            display: grid;
            flex: 1;
            align-content: start;
            gap: 4px;
            padding: 8px;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 18px;
            min-height: 44px;
            padding: 0 12px;
            overflow: hidden;
            border-radius: 6px;
            color: var(--kinta-primary-dark);
            font-weight: 600;
            white-space: nowrap;
            transition: background-color .15s ease, color .15s ease;
        }

        .admin-nav a:hover,
        .admin-nav a:focus-visible {
            color: #172033;
            background: var(--kinta-secondary);
            text-decoration: none;
        }

        .admin-nav a.active {
            color: #073b64;
            background: #dbeafe;
            font-weight: 700;
        }

        .nav-icon {
            flex: 0 0 20px;
            width: 20px;
            color: #64748b;
            font-size: 1.1rem;
            text-align: center;
        }

        .nav-label {
            opacity: 0;
            transition: opacity .12s ease;
        }

        .sidebar-signout {
            padding: 8px;
        }

        .sidebar-signout form { margin: 0; }

        .sidebar-signout button {
            display: flex;
            align-items: center;
            gap: 18px;
            width: 100%;
            min-height: 44px;
            margin: 0;
            padding: 0 12px;
            overflow: hidden;
            border: 0;
            border-radius: 6px;
            color: var(--kinta-primary-dark);
            background: white;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
            transition: background-color .15s ease, color .15s ease;
        }

        .sidebar-signout button:hover,
        .sidebar-signout button:focus-visible {
            color: #172033;
            background: var(--kinta-secondary);
        }

        .sidebar:hover .nav-label,
        .sidebar:hover .brand-label,
        .sidebar:focus-within .nav-label,
        .sidebar:focus-within .brand-label { opacity: 1; }

        .page-content {
            min-height: 100vh;
            margin-left: 60px;
            padding: 1px 20px 24px;
        }

        .page-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            max-width: 1200px;
            margin: 22px auto 16px;
        }

        .page-heading {
            margin: 0;
            color: #172033;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 5px 0 0;
            padding: 0;
            color: #64748b;
            font-size: .85rem;
            list-style: none;
        }

        .breadcrumb-separator { color: #94a3b8; }

        main {
            max-width: 1200px;
            margin: 0 auto;
        }

        section,
        .box {
            margin: 12px 0;
            padding: 18px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px #1111;
        }

        input,
        select,
        button {
            margin: 8px 10px 8px 0;
            padding: 9px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font: inherit;
        }

        button {
            border: 0;
            color: white;
            background: var(--kinta-primary);
            cursor: pointer;
        }

        button:hover,
        button:focus-visible { background: var(--kinta-primary-hover); }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 9px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .dashboard-cards {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            padding: 4px 2px 12px;
        }

        .cards a {
            padding: 22px;
            border-radius: 9px;
            background: white;
            font-weight: 600;
        }

        .dashboard-cards a {
            flex: 1 0 145px;
            min-width: 145px;
            text-decoration: none;
            transition: background-color .15s ease;
        }

        .dashboard-cards a:hover,
        .dashboard-cards a:focus-visible {
            background: var(--kinta-secondary);
            text-decoration: none;
        }

        .dashboard-section { padding-top: 14px; }

        .dashboard-disclosure {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin: 0 0 12px;
            padding: 8px 10px;
            border: 0;
            border-radius: 6px;
            color: #172033;
            background: transparent;
            font-size: 1.1rem;
            font-weight: 700;
            text-align: left;
        }

        .dashboard-disclosure:hover,
        .dashboard-disclosure:focus-visible {
            color: #172033;
            background: var(--kinta-secondary);
            text-decoration: none;
        }

        .dashboard-disclosure-arrow {
            display: inline-block;
            margin-left: 12px;
            transition: transform .15s ease;
        }

        .dashboard-disclosure[aria-expanded="true"] .dashboard-disclosure-arrow {
            transform: rotate(180deg);
        }

        .dashboard-section-content[hidden] { display: none; }

        .dashboard-chart-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            align-items: stretch;
        }

        .dashboard-chart-card {
            min-width: 0;
            min-height: 390px;
            margin: 12px 0;
            padding: 18px;
            overflow: hidden;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px #1111;
        }

        .dashboard-chart-card h2 {
            margin: 0 0 14px;
            color: #172033;
            font-size: 1.1rem;
        }

        .dashboard-chart-canvas {
            position: relative;
            width: 100%;
            height: 320px;
        }

        .dashboard-chart-empty {
            display: grid;
            min-height: 320px;
            place-items: center;
            margin: 0;
            text-align: center;
        }

        @media (max-width: 760px) {
            .dashboard-chart-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .dashboard-chart-card {
                min-height: 350px;
            }

            .dashboard-chart-canvas { height: 280px; }
        }

        .muted { color: #64748b; }
        .error { color: #b91c1c; }
        .inline { display: inline; }
        .inactive { color: #b91c1c; }

        nav:not(.admin-nav) {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            padding: 12px 24px;
            background: white;
        }

        .view-record {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--kinta-primary-dark);
        }

        .view-record:hover,
        .view-record:focus-visible { color: #374151; }
        .view-record svg { width: 20px; height: 20px; }

        .operation-report-filters {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px;
            border-radius: 8px;
            background: #fff;
        }

        .operation-report-filters label {
            display: grid;
            gap: 4px;
            min-width: 150px;
        }

        .operation-report-filters .select2-container {
            min-width: 150px;
            font-size: 0.95rem;
        }

        .operation-report-table-scroll,
        .process-history-scroll {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .process-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            line-height: 1.25;
            white-space: nowrap;
        }

        .process-status-badge.is-complete { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .process-status-badge.is-running { color: #075985; background: #e0f2fe; border-color: #bae6fd; }
        .process-status-badge.is-stopped { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
        .process-status-badge.is-neutral { color: #475569; background: #f1f5f9; border-color: #cbd5e1; }

        .process-details-summary { padding: 20px; }
        .process-details-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .process-details-heading h2 { margin: 0 0 16px; }
        .process-details-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; }
        .process-details-grid > div { min-width: 0; padding: 12px; border-radius: 7px; background: #f8fafc; }
        .process-details-grid dt { margin-bottom: 5px; color: #64748b; font-size: 0.82rem; font-weight: 600; }
        .process-details-grid dd { margin: 0; overflow-wrap: anywhere; font-weight: 600; }
        .process-details-grid .process-details-wide { grid-column: 1 / -1; }
        .process-details-grid .process-details-remarks { white-space: normal; font-weight: 400; }
        .process-history-section h2 { margin-top: 0; }
        .process-history-section table { min-width: 520px; }

        @media (max-width: 600px) {
            .page-content { padding-right: 12px; padding-left: 12px; }
            .page-toolbar { align-items: flex-start; }
            section, .box { padding: 14px; overflow-x: auto; }
            .process-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 420px) {
            .process-details-grid { grid-template-columns: 1fr; }
            .process-details-grid .process-details-wide { grid-column: auto; }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 240px;
                transform: translateX(-100%);
                visibility: hidden;
            }

            .sidebar:hover,
            .sidebar:focus-within { width: 240px; }

            .sidebar.is-open {
                transform: translateX(0);
                visibility: visible;
            }

            .sidebar .brand-label,
            .sidebar .nav-label { opacity: 1; }

            .page-content {
                margin-left: 0;
                padding-top: 64px;
            }

            .sidebar-toggle { left: 12px; }
            body.sidebar-expanded .sidebar-toggle { left: 222px; }
        }
    </style>
    <?php if ($masterDataModule ?? false): ?>
        <style>
            .master-data-table {
                min-width: 680px;
            }

            .master-data-table-scroll {
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .master-data-table-wrapper .dt-search input {
                border: 0 !important;
                outline: none;
                box-shadow: none;
            }

            .master-data-table-wrapper .dt-length {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
            }

            .master-data-table-wrapper .kinta-table-icon-button {
                display: inline-grid;
                width: 2.25rem;
                height: 2.25rem;
                place-items: center;
                padding: 0;
                border: 0;
                border-radius: 0.4rem;
                color: #334155;
                background: transparent;
                cursor: pointer;
                font-size: 1.05rem;
            }

            .master-data-table-wrapper .kinta-table-icon-button:hover,
            .master-data-table-wrapper .kinta-table-icon-button.is-active {
                color: var(--kinta-primary-dark);
                background: #e2e8f0;
            }

            .master-data-table-wrapper .kinta-table-icon-button:focus-visible {
                outline: 2px solid var(--kinta-primary);
                outline-offset: 2px;
            }

            .master-data-table-wrapper .kinta-table-search-control {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }

            .master-data-table-wrapper .kinta-filter-panel {
                position: absolute;
                z-index: 1000;
                top: calc(100% + 0.5rem);
                right: 0;
                display: grid;
                width: min(340px, calc(100vw - 2rem));
                gap: 0.7rem;
                padding: 1rem;
                border: 1px solid #cbd5e1;
                border-radius: 0.6rem;
                background: #fff;
                box-shadow: 0 12px 28px rgb(15 23 42 / 18%);
            }

            .master-data-table-wrapper .kinta-filter-panel[hidden] {
                display: none;
            }

            .master-data-table-wrapper .kinta-filter-field {
                display: grid;
                gap: 0.25rem;
                color: #334155;
                font-size: 0.9rem;
                font-weight: 600;
            }

            .master-data-table-wrapper .kinta-filter-field input,
            .master-data-table-wrapper .kinta-filter-field select {
                width: 100%;
                min-height: 2.4rem;
                padding: 0.45rem 0.55rem;
                border: 1px solid #cbd5e1;
                border-radius: 0.35rem;
                color: #172033;
                background: #fff;
                font: inherit;
                font-weight: 400;
            }

            .master-data-table-wrapper .kinta-filter-field input {
                border: 1px solid #cbd5e1 !important;
                outline: none;
            }

            .master-data-table-wrapper .kinta-filter-field .select2-container {
                width: 100% !important;
                font-size: 0.9rem;
                font-weight: 400;
            }

            .master-data-table-wrapper .select2-container--default .select2-selection--single {
                min-height: 2.4rem;
                border: 1px solid #cbd5e1;
                border-radius: 0.35rem;
                background: #fff;
            }

            .master-data-table-wrapper .select2-container--default .select2-selection--single .select2-selection__rendered {
                padding-right: 2rem;
                padding-left: 0.55rem;
                color: #172033;
                line-height: calc(2.4rem - 2px);
            }

            .master-data-table-wrapper .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 2.4rem;
            }

            .master-data-table-wrapper .kinta-filter-panel .select2-container--open,
            .master-data-table-wrapper .kinta-filter-panel .select2-dropdown {
                z-index: 10001;
            }

            .master-data-table-wrapper .kinta-filter-clear {
                justify-self: start;
                padding: 0.45rem 0.65rem;
                border: 0;
                border-radius: 0.35rem;
                color: var(--kinta-primary-dark);
                background: var(--kinta-primary-soft);
                cursor: pointer;
            }

            @media (max-width: 600px) {
                .master-data-table-wrapper .kinta-filter-panel {
                    right: -0.25rem;
                }
            }

            .master-data-table-wrapper .dt-paging-button.current {
                border-color: var(--kinta-primary) !important;
                color: #fff !important;
                background: var(--kinta-primary) !important;
            }

            .master-data-table-wrapper .kinta-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.55rem;
                border: 1px solid transparent;
                border-radius: 999px;
                font-size: 0.82rem;
                font-weight: 600;
                line-height: 1.2;
                white-space: nowrap;
            }

            .master-data-table-wrapper .kinta-status-active {
                color: #166534;
                background: #dcfce7;
                border-color: #bbf7d0;
            }

            .master-data-table-wrapper .kinta-status-inactive {
                color: #374151;
                background: var(--kinta-secondary);
                border-color: #d1d5db;
            }

            .master-data-table-wrapper .kinta-status-neutral {
                color: #475569;
                background: #f1f5f9;
                border-color: #cbd5e1;
            }

            .master-data-table-wrapper .kinta-master-edit-button,
            .master-data-table-wrapper .kinta-master-edit-button:hover,
            .master-data-table-wrapper .kinta-master-edit-button:focus {
                border: 0;
                color: #334155;
                background: transparent;
                box-shadow: none;
            }

            #edit-form .select2-container {
                width: 100% !important;
                margin: 8px 10px 8px 0;
            }

            #edit-form .select2-container--default .select2-selection--single {
                height: 40px;
                border: 1px solid #cbd5e1;
                border-radius: 5px;
            }

            #edit-form .select2-container--default .select2-selection--single .select2-selection__rendered {
                padding-left: 9px;
                line-height: 38px;
            }

            #edit-form .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 38px;
            }
        </style>
    <?php endif ?>
    <?php if ($adminModalEnabled ?? false): ?>
        <style>
            .admin-form-modal {
                width: min(560px, calc(100vw - 2rem));
                max-width: none;
                max-height: 90vh;
                max-height: 90dvh;
                margin: auto;
                padding: 0;
                overflow: hidden;
                border: 0;
                border-radius: 10px;
                color: #172033;
                background: #fff;
                box-shadow: 0 16px 48px #1115;
            }

            .admin-form-modal::backdrop {
                background: rgb(15 23 42 / 55%);
            }

            .admin-form-modal__panel {
                display: flex;
                max-height: 90vh;
                max-height: 90dvh;
                flex-direction: column;
            }

            .admin-form-modal__header,
            .admin-form-modal__footer {
                display: flex;
                flex: 0 0 auto;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 18px;
                background: #f8fafc;
            }

            .admin-form-modal__header {
                border-bottom: 1px solid #e2e8f0;
            }

            .admin-form-modal__header h2 {
                margin: 0;
                font-size: 1.2rem;
            }

            .admin-form-modal__close {
                width: 36px;
                height: 36px;
                margin: 0;
                padding: 0;
                color: #334155;
                background: transparent;
                font-size: 1.5rem;
            }

            .admin-form-modal__close:hover,
            .admin-form-modal__close:focus-visible {
                color: #172033;
                background: #e2e8f0;
            }

            .admin-form-modal__body {
                min-height: 0;
                overflow-y: auto;
                padding: 18px;
                overscroll-behavior: contain;
            }

            .admin-form-modal__body form {
                margin: 0;
            }

            .admin-form-modal__body label {
                display: block;
                margin-bottom: 10px;
            }

            .admin-form-modal__body input,
            .admin-form-modal__body select {
                width: 100%;
                margin-right: 0;
            }

            #activity-form-modal #edit-form .select2-container {
                margin-right: 0;
            }

            #workforce-form-modal #edit-form .select2-container {
                margin-right: 0;
            }

            .admin-form-modal__error {
                flex: 0 0 auto;
                margin: 12px 18px 0;
            }

            .admin-form-modal__footer {
                justify-content: flex-end;
                border-top: 1px solid #e2e8f0;
            }

            #activity-form-modal .select2-container--open,
            #activity-form-modal .select2-dropdown,
            #workforce-form-modal .select2-container--open,
            #workforce-form-modal .select2-dropdown {
                z-index: 10001;
            }

            @media (max-width: 600px) {
                .admin-form-modal {
                    width: calc(100vw - 1rem);
                    max-height: calc(100dvh - 1rem);
                }

                .admin-form-modal__panel {
                    max-height: calc(100dvh - 1rem);
                }

                .admin-form-modal__header,
                .admin-form-modal__body,
                .admin-form-modal__footer {
                    padding: 12px;
                }
            }
        </style>
    <?php endif ?>
