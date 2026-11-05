-- MotoStock · esquema inicial
-- Compatível com MySQL 8.0.16+ e MariaDB 10.6+. Datas em UTC. Dinheiro em centavos.
-- Toda tabela de loja tem tenant_id e UNIQUE (tenant_id, id) para permitir FKs compostas,
-- que impedem no banco referências entre lojas diferentes.

CREATE TABLE plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  billing_interval ENUM('month','year') NOT NULL DEFAULT 'month',
  trial_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_users INT UNSIGNED NULL COMMENT 'NULL = sem limite',
  max_products INT UNSIGNED NULL,
  max_orders_month INT UNSIGNED NULL,
  allow_custom_domain TINYINT(1) NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  slug VARCHAR(40) NOT NULL,
  trade_name VARCHAR(120) NOT NULL,
  legal_name VARCHAR(160) NOT NULL,
  document VARCHAR(18) NOT NULL COMMENT 'CNPJ somente dígitos',
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(30) NULL,
  address_line VARCHAR(200) NULL,
  city VARCHAR(80) NULL,
  state CHAR(2) NULL,
  zip VARCHAR(9) NULL,
  status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  timezone VARCHAR(40) NOT NULL DEFAULT 'America/Sao_Paulo',
  trial_ends_at DATETIME NULL,
  suspended_at DATETIME NULL,
  status_reason VARCHAR(255) NULL,
  cancelled_at DATETIME NULL,
  retention_until DATETIME NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_tenants_uuid (uuid),
  UNIQUE KEY uq_tenants_slug (slug),
  KEY ix_tenants_status (status),
  CONSTRAINT fk_tenants_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_domains (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  host VARCHAR(190) NOT NULL,
  kind ENUM('subdomain','custom') NOT NULL,
  verification_token CHAR(32) NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_domains_host (host),
  KEY ix_domains_tenant (tenant_id),
  CONSTRAINT fk_domains_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  status ENUM('trialing','active','past_due','cancelled') NOT NULL,
  current_period_start DATETIME NULL,
  current_period_end DATETIME NULL,
  gateway VARCHAR(30) NULL,
  external_id VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_subs_tenant (tenant_id),
  CONSTRAINT fk_subs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  platform_role ENUM('admin','support') NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  mfa_secret TEXT NULL COMMENT 'cifrado com APP_KEY',
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('owner','manager','seller') NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_tenant_user (tenant_id, user_id),
  UNIQUE KEY uq_tenant_users_tid (tenant_id, id),
  KEY ix_tenant_users_user (user_id),
  CONSTRAINT fk_tu_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_resets_token (token_hash),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(40) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_rate (bucket, key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  area ENUM('platform','panel','store','system') NOT NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(60) NULL,
  entity_id VARCHAR(40) NULL,
  ip VARCHAR(45) NULL,
  meta TEXT NULL COMMENT 'JSON sem senhas, tokens ou dados de cartão',
  created_at DATETIME NOT NULL,
  KEY ix_audit_tenant (tenant_id, created_at),
  KEY ix_audit_area (area, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_counters (
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(30) NOT NULL,
  value INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tenant_id, name),
  CONSTRAINT fk_counters_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE store_settings (
  tenant_id INT UNSIGNED NOT NULL PRIMARY KEY,
  contact_email VARCHAR(160) NULL,
  contact_phone VARCHAR(30) NULL,
  whatsapp VARCHAR(30) NULL,
  service_hours VARCHAR(120) NULL,
  about TEXT NULL,
  policy_privacy MEDIUMTEXT NULL,
  policy_returns MEDIUMTEXT NULL,
  policy_shipping MEDIUMTEXT NULL,
  policy_version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gateway_credentials (
  tenant_id INT UNSIGNED NOT NULL,
  gateway VARCHAR(30) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  sandbox TINYINT(1) NOT NULL DEFAULT 1,
  public_key VARCHAR(200) NULL,
  access_token_enc TEXT NULL COMMENT 'cifrado com APP_KEY',
  webhook_secret_enc TEXT NULL COMMENT 'cifrado com APP_KEY',
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id, gateway),
  CONSTRAINT fk_gw_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Catálogo =====

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(90) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_categories_tid (tenant_id, id),
  UNIQUE KEY uq_categories_slug (tenant_id, slug),
  CONSTRAINT fk_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_brands_tid (tenant_id, id),
  UNIQUE KEY uq_brands_name (tenant_id, name),
  CONSTRAINT fk_brands_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  sku VARCHAR(60) NOT NULL,
  barcode VARCHAR(60) NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  category_id INT UNSIGNED NULL,
  brand_id INT UNSIGNED NULL,
  cost_cents INT UNSIGNED NOT NULL DEFAULT 0,
  price_cents INT UNSIGNED NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  reserved_qty INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 0 COMMENT 'com sinal: entra em subtrações (disponível - mínimo)',
  weight_grams INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_products_tid (tenant_id, id),
  UNIQUE KEY uq_products_sku (tenant_id, sku),
  UNIQUE KEY uq_products_slug (tenant_id, slug),
  KEY ix_products_barcode (tenant_id, barcode),
  KEY ix_products_category (tenant_id, category_id),
  KEY ix_products_brand (tenant_id, brand_id),
  CONSTRAINT ck_products_stock CHECK (stock_qty >= 0 AND reserved_qty >= 0 AND reserved_qty <= stock_qty),
  CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (tenant_id, category_id) REFERENCES categories(tenant_id, id),
  CONSTRAINT fk_products_brand FOREIGN KEY (tenant_id, brand_id) REFERENCES brands(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  file_name VARCHAR(80) NOT NULL,
  mime VARCHAR(30) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  alt VARCHAR(160) NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_images_tid (tenant_id, id),
  KEY ix_images_product (tenant_id, product_id),
  CONSTRAINT fk_images_product FOREIGN KEY (tenant_id, product_id) REFERENCES products(tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_fitments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  make VARCHAR(60) NOT NULL COMMENT 'marca da moto',
  model VARCHAR(80) NOT NULL,
  year_from SMALLINT UNSIGNED NOT NULL,
  year_to SMALLINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_fitments_product (tenant_id, product_id),
  KEY ix_fitments_lookup (tenant_id, make, model, year_from, year_to),
  CONSTRAINT ck_fitments_years CHECK (year_to >= year_from),
  CONSTRAINT fk_fitments_product FOREIGN KEY (tenant_id, product_id) REFERENCES products(tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Fornecedores e compras =====

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  document VARCHAR(18) NULL,
  contact_name VARCHAR(120) NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(30) NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_suppliers_tid (tenant_id, id),
  KEY ix_suppliers_name (tenant_id, name),
  CONSTRAINT fk_suppliers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  number INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  status ENUM('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
  expected_at DATE NULL,
  notes TEXT NULL,
  total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  received_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_po_tid (tenant_id, id),
  UNIQUE KEY uq_po_number (tenant_id, number),
  KEY ix_po_supplier (tenant_id, supplier_id),
  CONSTRAINT fk_po_supplier FOREIGN KEY (tenant_id, supplier_id) REFERENCES suppliers(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  quantity_received INT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost_cents INT UNSIGNED NOT NULL,
  KEY ix_poi_po (tenant_id, purchase_order_id),
  KEY ix_poi_product (tenant_id, product_id),
  CONSTRAINT ck_poi_received CHECK (quantity_received <= quantity),
  CONSTRAINT fk_poi_po FOREIGN KEY (tenant_id, purchase_order_id) REFERENCES purchase_orders(tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_product FOREIGN KEY (tenant_id, product_id) REFERENCES products(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Clientes =====

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(30) NULL,
  document VARCHAR(14) NULL COMMENT 'CPF/CNPJ, opcional',
  password_hash VARCHAR(255) NULL COMMENT 'NULL = sem conta na loja',
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  privacy_accepted_at DATETIME NULL,
  privacy_version INT UNSIGNED NULL,
  anonymized_at DATETIME NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_customers_tid (tenant_id, id),
  UNIQUE KEY uq_customers_email (tenant_id, email),
  KEY ix_customers_name (tenant_id, name),
  CONSTRAINT fk_customers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  zip VARCHAR(9) NOT NULL,
  street VARCHAR(160) NOT NULL,
  number VARCHAR(20) NOT NULL,
  complement VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  city VARCHAR(80) NOT NULL,
  state CHAR(2) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_addr_customer (tenant_id, customer_id),
  CONSTRAINT fk_addr_customer FOREIGN KEY (tenant_id, customer_id) REFERENCES customers(tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  email VARCHAR(160) NOT NULL,
  type ENUM('access','correction','deletion','portability','other') NOT NULL,
  message TEXT NULL,
  status ENUM('open','done','rejected') NOT NULL DEFAULT 'open',
  resolution TEXT NULL,
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_dr_tenant (tenant_id, status),
  CONSTRAINT fk_dr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Pedidos, pagamentos e entrega =====

CREATE TABLE shipping_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  kind ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  free_above_cents INT UNSIGNED NULL,
  days_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  days_max SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  regions VARCHAR(255) NULL COMMENT 'UFs atendidas separadas por vírgula (vazio = todas)',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_ship_tid (tenant_id, id),
  CONSTRAINT fk_ship_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  number INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  channel ENUM('store','manual') NOT NULL,
  status ENUM('awaiting_payment','paid','picking','shipped','delivered','cancelled','refunded') NOT NULL,
  subtotal_cents INT UNSIGNED NOT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_cents INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents INT UNSIGNED NOT NULL,
  payment_method VARCHAR(30) NULL,
  shipping_method_id INT UNSIGNED NULL,
  shipping_name VARCHAR(80) NULL,
  shipping_days VARCHAR(60) NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(160) NULL,
  customer_phone VARCHAR(30) NULL,
  ship_zip VARCHAR(9) NULL,
  ship_street VARCHAR(160) NULL,
  ship_number VARCHAR(20) NULL,
  ship_complement VARCHAR(80) NULL,
  ship_district VARCHAR(80) NULL,
  ship_city VARCHAR(80) NULL,
  ship_state CHAR(2) NULL,
  notes TEXT NULL,
  tracking_code VARCHAR(60) NULL,
  tracking_token_hash CHAR(64) NULL,
  operator_user_id INT UNSIGNED NULL,
  privacy_version INT UNSIGNED NULL,
  placed_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_orders_tid (tenant_id, id),
  UNIQUE KEY uq_orders_number (tenant_id, number),
  KEY ix_orders_status (tenant_id, status),
  KEY ix_orders_placed (tenant_id, placed_at),
  KEY ix_orders_paid (tenant_id, paid_at),
  KEY ix_orders_customer (tenant_id, customer_id),
  CONSTRAINT fk_orders_customer FOREIGN KEY (tenant_id, customer_id) REFERENCES customers(tenant_id, id),
  CONSTRAINT fk_orders_shipping FOREIGN KEY (tenant_id, shipping_method_id) REFERENCES shipping_methods(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  unit_price_cents INT UNSIGNED NOT NULL,
  unit_cost_cents INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL,
  total_cents INT UNSIGNED NOT NULL,
  fitment_note VARCHAR(160) NULL COMMENT 'moto informada pelo cliente ao confirmar compatibilidade',
  KEY ix_items_order (tenant_id, order_id),
  KEY ix_items_product (tenant_id, product_id),
  CONSTRAINT fk_items_order FOREIGN KEY (tenant_id, order_id) REFERENCES orders(tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (tenant_id, product_id) REFERENCES products(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  actor ENUM('staff','customer','gateway','system') NOT NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_osh_order (tenant_id, order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (tenant_id, order_id) REFERENCES orders(tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('entry','exit','adjust','return','reserve','release') NOT NULL,
  quantity INT NOT NULL COMMENT 'variação com sinal',
  stock_after INT NOT NULL,
  reserved_after INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  user_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  purchase_order_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_sm_product (tenant_id, product_id, created_at),
  KEY ix_sm_created (tenant_id, created_at),
  CONSTRAINT fk_sm_product FOREIGN KEY (tenant_id, product_id) REFERENCES products(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  uuid CHAR(36) NOT NULL,
  gateway VARCHAR(30) NOT NULL,
  method VARCHAR(30) NOT NULL,
  status ENUM('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
  amount_cents INT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(80) NOT NULL,
  external_id VARCHAR(100) NULL,
  checkout_url VARCHAR(500) NULL,
  status_detail VARCHAR(120) NULL,
  recorded_by INT UNSIGNED NULL COMMENT 'usuário, quando registrado manualmente no balcão',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_payments_uuid (uuid),
  UNIQUE KEY uq_payments_idem (tenant_id, idempotency_key),
  KEY ix_payments_order (tenant_id, order_id),
  KEY ix_payments_external (gateway, external_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (tenant_id, order_id) REFERENCES orders(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gateway VARCHAR(30) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  tenant_id INT UNSIGNED NULL,
  payload_sha256 CHAR(64) NOT NULL,
  status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  UNIQUE KEY uq_webhook_event (gateway, event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estado do gateway simulado (somente desenvolvimento e testes).
CREATE TABLE simulated_gateway_payments (
  external_id VARCHAR(40) NOT NULL PRIMARY KEY,
  reference CHAR(36) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  kind VARCHAR(30) NOT NULL,
  file_name VARCHAR(160) NOT NULL,
  rows_total INT UNSIGNED NOT NULL DEFAULT 0,
  rows_ok INT UNSIGNED NOT NULL DEFAULT 0,
  rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
  errors MEDIUMTEXT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_import_tenant (tenant_id, created_at),
  CONSTRAINT fk_import_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
