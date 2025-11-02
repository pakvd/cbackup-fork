# 📊 Интерактивные диаграммы архитектуры cBackup

## 🔷 Логическая схема (Mermaid)

```mermaid
graph TB
    User[👤 Пользователь<br/>Браузер]
    
    subgraph "Внешний доступ"
        Nginx[🌐 NGINX<br/>Веб-сервер<br/>Порт: 8080/443]
    end
    
    subgraph "Внутренняя сеть Docker (cbackup_network)"
        Web[🐘 Web Container<br/>PHP-FPM + Yii2<br/>Порт: 9000]
        
        subgraph "Web Components"
            Controllers[Контроллеры<br/>Config, Node, Task]
            Components[Компоненты<br/>NetSsh, Service, Telnet]
            API[API Endpoints<br/>v1/core/*, v2/core/*]
        end
        
        Worker[☕ Worker Container<br/>Java Spring Boot]
        
        subgraph "Worker Components"
            Scheduler[📅 Scheduler<br/>Планировщик задач]
            Workers[🔧 Workers<br/>SSH, Telnet, SNMP]
            APIClient[📡 API Client<br/>HTTP запросы к Web]
        end
        
        DB[(🗄️ MySQL<br/>База данных<br/>Порт: 3306)]
        Redis[(💾 Redis<br/>Кеш<br/>Порт: 6379)]
    end
    
    subgraph "External"
        Devices[🌐 Сетевое оборудование<br/>SSH/Telnet/SNMP]
    end
    
    subgraph "Shared Resources"
        SharedVolume[📁 Shared Volume<br/>core/bin/<br/>application.properties]
    end
    
    User -->|HTTP/HTTPS| Nginx
    Nginx -->|FastCGI :9000| Web
    Web -->|MySQL :3306| DB
    Web -->|Redis :6379| Redis
    Web -->|HTTP API| Worker
    Worker -->|MySQL JDBC :3306| DB
    Worker -->|SSH/Telnet/SNMP| Devices
    Web -.->|Чтение/Запись| SharedVolume
    Worker -.->|Чтение/Запись| SharedVolume
    
    Web --> Controllers
    Web --> Components
    Web --> API
    
    Worker --> Scheduler
    Worker --> Workers
    Worker --> APIClient
    
    style User fill:#e1f5ff
    style Nginx fill:#fff4e1
    style Web fill:#e8f5e9
    style Worker fill:#fff3e0
    style DB fill:#f3e5f5
    style Redis fill:#ffebee
    style Devices fill:#e0f2f1
    style SharedVolume fill:#fce4ec
```

## 🌐 Сетевая схема (Mermaid)

```mermaid
graph TB
    subgraph "Docker Host"
        subgraph "Bridge Network: cbackup_network"
            direction TB
            
            Nginx[nginx:alpine<br/>Port: 80<br/>Exposed: 8080:80]
            
            Web[PHP-FPM Container<br/>Port: 9000<br/>Yii2 Application]
            
            Worker[Java Container<br/>Spring Boot<br/>SSH Shell: 8437]
            
            DB[(MySQL 8.0<br/>Port: 3306<br/>Not Exposed)]
            
            Redis[(Redis 7-alpine<br/>Port: 6379<br/>Not Exposed)]
        end
        
        SharedVol[Shared Volume<br/>core/bin/<br/>application.properties]
    end
    
    Internet[🌍 Интернет]
    NetworkDevices[🌐 Сетевое оборудование]
    
    Internet -->|:8080 HTTP| Nginx
    Internet -->|:443 HTTPS| Nginx
    Nginx -->|FastCGI :9000| Web
    Web -->|MySQL :3306| DB
    Web -->|Redis :6379| Redis
    Web -->|HTTP REST API| Worker
    Worker -->|MySQL JDBC :3306| DB
    Worker -->|SSH/Telnet/SNMP| NetworkDevices
    
    Web -.-> SharedVol
    Worker -.-> SharedVol
    
    style Internet fill:#e3f2fd
    style Nginx fill:#fff9c4
    style Web fill:#c8e6c9
    style Worker fill:#ffe0b2
    style DB fill:#e1bee7
    style Redis fill:#ffcdd2
    style NetworkDevices fill:#b2dfdb
    style SharedVol fill:#f8bbd0
```

## 🔄 Поток выполнения задачи бэкапа (Mermaid)

```mermaid
sequenceDiagram
    participant User as 👤 Пользователь
    participant Nginx as 🌐 NGINX
    participant Web as 🐘 Web (PHP)
    participant DB as 🗄️ MySQL
    participant Worker as ☕ Worker (Java)
    participant Device as 🌐 Устройство
    
    Note over User,Device: 1. Создание задачи через веб-интерфейс
    User->>Nginx: HTTP POST /index.php?r=task/create
    Nginx->>Web: FastCGI
    Web->>DB: INSERT INTO tasks
    DB-->>Web: Success
    Web-->>Nginx: Response
    Nginx-->>User: Redirect
    
    Note over Worker,Device: 2. Планировщик получает задачу
    Worker->>DB: SELECT tasks WHERE schedule_time = NOW()
    DB-->>Worker: Task list
    
    Note over Worker,Web: 3. Получение конфигурации
    Worker->>Web: HTTP GET /index.php?v1/core/get-config
    Web->>DB: SELECT config
    DB-->>Web: Config data
    Web-->>Worker: JSON config
    
    Note over Worker,Device: 4. Выполнение бэкапа
    Worker->>Device: SSH/Telnet Connect
    Device-->>Worker: Connected
    Worker->>Device: Execute backup commands
    Device-->>Worker: Configuration data
    
    Note over Worker,DB: 5. Сохранение результата
    Worker->>DB: INSERT INTO backup_data
    DB-->>Worker: Success
    
    Note over Worker,Web: 6. Отправка логов
    Worker->>Web: HTTP POST /index.php?v1/core/set-log
    Web->>DB: INSERT INTO logs
    DB-->>Web: Success
    Web-->>Worker: OK
```

## 📊 Схема компонентов приложения (Mermaid)

```mermaid
graph LR
    subgraph "Web Layer (PHP)"
        A[Yii2 Framework]
        B[Controllers]
        C[Models]
        D[Components]
        E[Views]
    end
    
    subgraph "Worker Layer (Java)"
        F[Spring Boot]
        G[Scheduler]
        H[Workers]
        I[API Client]
    end
    
    subgraph "Data Layer"
        J[(MySQL)]
        K[(Redis)]
    end
    
    subgraph "External"
        L[Network Devices]
    end
    
    A --> B
    A --> C
    A --> D
    A --> E
    B --> C
    D --> J
    D --> K
    C --> J
    
    F --> G
    F --> H
    F --> I
    G --> J
    H --> L
    I --> B
    
    style A fill:#4caf50
    style F fill:#ff9800
    style J fill:#9c27b0
    style K fill:#f44336
```

## 🔌 Схема портов и подключений (Mermaid)

```mermaid
graph TB
    subgraph "External Ports"
        EP1[8080 - HTTP]
        EP2[443 - HTTPS]
    end
    
    subgraph "Internal Ports"
        IP1[80 - NGINX]
        IP2[9000 - PHP-FPM]
        IP3[3306 - MySQL]
        IP4[6379 - Redis]
        IP5[8437 - SSH Shell]
    end
    
    subgraph "Services"
        S1[NGINX]
        S2[Web PHP]
        S3[MySQL]
        S4[Redis]
        S5[Worker Java]
    end
    
    EP1 --> IP1
    EP2 --> IP1
    IP1 --> IP2
    IP2 --> IP3
    IP2 --> IP4
    IP3 --> S3
    IP4 --> S4
    IP2 -->|API| S5
    S5 --> IP3
    S5 --> IP5
    
    style EP1 fill:#ff9800
    style EP2 fill:#ff9800
    style IP1 fill:#4caf50
    style IP2 fill:#2196f3
    style IP3 fill:#9c27b0
    style IP4 fill:#f44336
    style IP5 fill:#ff5722
```

## 🗄️ Схема базы данных (Mermaid)

```mermaid
erDiagram
    CONFIG ||--o{ TASKS : "configures"
    USERS ||--o{ LOGS : "creates"
    NODES ||--o{ TASKS : "has"
    TASKS ||--o{ SCHEDULES : "scheduled_by"
    TASKS ||--o{ LOG_NODE : "logs_to"
    CREDENTIALS ||--o{ NODES : "authenticates"
    
    CONFIG {
        string key PK
        string value
    }
    
    USERS {
        int id PK
        string username
        string password
        string email
    }
    
    NODES {
        int id PK
        string ip
        string vendor
        string model
        int credential_id FK
    }
    
    TASKS {
        int id PK
        string name
        string type
        string put
    }
    
    SCHEDULES {
        int id PK
        int task_id FK
        string cron
        int node_id FK
    }
    
    CREDENTIALS {
        int id PK
        string ssh_login
        string ssh_password
    }
    
    LOGS {
        int id PK
        int user_id FK
        string action
        timestamp created_at
    }
    
    LOG_NODE {
        int id PK
        int task_id FK
        int node_id FK
        string status
    }
```

## 📦 Схема Docker Compose (Mermaid)

```mermaid
graph TB
    subgraph "docker-compose.yml"
        DC[Docker Compose]
    end
    
    subgraph "Services"
        NGINX_SVC[nginx:alpine]
        WEB_SVC[web: custom build]
        WORKER_SVC[worker: custom build]
        DB_SVC[mysql:8.0]
        REDIS_SVC[redis:7-alpine]
    end
    
    subgraph "Volumes"
        DB_VOL[db_data]
        REDIS_VOL[redis_data]
        WEB_RUNTIME[web_runtime]
        SHARED_BIN[core/bin]
    end
    
    subgraph "Network"
        NET[cbackup_network<br/>driver: bridge]
    end
    
    DC --> NGINX_SVC
    DC --> WEB_SVC
    DC --> WORKER_SVC
    DC --> DB_SVC
    DC --> REDIS_SVC
    
    NGINX_SVC --> NET
    WEB_SVC --> NET
    WORKER_SVC --> NET
    DB_SVC --> NET
    REDIS_SVC --> NET
    
    DB_SVC --> DB_VOL
    REDIS_SVC --> REDIS_VOL
    WEB_SVC --> WEB_RUNTIME
    WEB_SVC --> SHARED_BIN
    WORKER_SVC --> SHARED_BIN
    
    style DC fill:#2196f3
    style NET fill:#4caf50
    style DB_VOL fill:#9c27b0
    style REDIS_VOL fill:#f44336
    style SHARED_BIN fill:#ff9800
```

