-- -----------------------------------------------------------------------------
-- Foco Hotel API - Modelagem do banco de dados (MySQL 8)
--
-- Espelha as migrations em database/migrations. Para abrir no MySQL Workbench:
--   File > Import > Reverse Engineer MySQL Create Script... > selecione este arquivo
-- e o diagrama EER é gerado automaticamente.
--
-- Origem dos dados (XML):
--   hotels.xml   -> hotels      (Hotel@id -> external_code, Name -> name)
--   rooms.xml    -> rooms       (Room@id -> external_code, Room@hotelCode -> hotel_id)
--   reserves.xml -> reserves    (Reserve@id/@hotelCode/@roomCode, CheckIn, CheckOut, Total)
--                   guests + guest_reserve (Guests/Guest)
--                   dailies     (Dailies/Daily)
--                   payments    (Payments/Payment)
-- -----------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS foco_hotel DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foco_hotel;

CREATE TABLE hotels (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_code       VARCHAR(50)     NULL COMMENT 'Código do hotel no sistema de origem (XML)',
    name                VARCHAR(150)    NOT NULL,
    service_fee_percent DECIMAL(5,2)    NOT NULL DEFAULT 0 COMMENT 'Taxa de serviço aplicada às reservas',
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY hotels_external_code_unique (external_code)
) ENGINE=InnoDB;

CREATE TABLE users (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hotel_id       BIGINT UNSIGNED NULL,
    name           VARCHAR(120)    NOT NULL,
    email          VARCHAR(255)    NOT NULL,
    password       VARCHAR(255)    NOT NULL,
    role           VARCHAR(20)     NOT NULL DEFAULT 'receptionist' COMMENT 'admin | manager | receptionist',
    remember_token VARCHAR(100)    NULL,
    created_at     TIMESTAMP       NULL,
    updated_at     TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email),
    CONSTRAINT users_hotel_id_foreign FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE personal_access_tokens (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tokenable_type VARCHAR(255)    NOT NULL,
    tokenable_id   BIGINT UNSIGNED NOT NULL,
    name           VARCHAR(255)    NOT NULL,
    token          VARCHAR(64)     NOT NULL,
    abilities      TEXT            NULL,
    last_used_at   TIMESTAMP       NULL,
    expires_at     TIMESTAMP       NULL,
    created_at     TIMESTAMP       NULL,
    updated_at     TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY personal_access_tokens_token_unique (token),
    KEY personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    hotel_id      BIGINT UNSIGNED   NOT NULL,
    external_code VARCHAR(50)       NULL COMMENT 'Código do quarto no sistema de origem (XML)',
    name          VARCHAR(120)      NOT NULL,
    description   TEXT              NULL,
    capacity      TINYINT UNSIGNED  NOT NULL DEFAULT 2 COMMENT 'Máximo de hóspedes por unidade',
    inventory     SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantidade de unidades disponíveis desta acomodação',
    daily_price   DECIMAL(10,2)     NULL COMMENT 'Tarifa base da diária',
    created_at    TIMESTAMP         NULL,
    updated_at    TIMESTAMP         NULL,
    deleted_at    TIMESTAMP         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY rooms_external_code_unique (external_code),
    CONSTRAINT rooms_hotel_id_foreign FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE guests (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100)    NOT NULL,
    last_name  VARCHAR(100)    NOT NULL,
    phone      VARCHAR(20)     NOT NULL,
    email      VARCHAR(255)    NULL,
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY guests_name_last_name_phone_unique (name, last_name, phone),
    KEY guests_phone_index (phone)
) ENGINE=InnoDB;

CREATE TABLE coupons (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hotel_id    BIGINT UNSIGNED NULL COMMENT 'Nulo = cupom válido para todos os hotéis',
    code        VARCHAR(40)     NOT NULL,
    type        VARCHAR(10)     NOT NULL COMMENT 'percent | fixed',
    value       DECIMAL(10,2)   NOT NULL,
    valid_from  DATE            NULL,
    valid_until DATE            NULL,
    max_uses    INT UNSIGNED    NULL,
    used_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NULL,
    updated_at  TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY coupons_code_unique (code),
    CONSTRAINT coupons_hotel_id_foreign FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE promotions (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hotel_id         BIGINT UNSIGNED NOT NULL,
    room_id          BIGINT UNSIGNED NULL COMMENT 'Nulo = promoção válida para todos os quartos do hotel',
    name             VARCHAR(120)    NOT NULL,
    discount_percent DECIMAL(5,2)    NOT NULL,
    starts_at        DATE            NOT NULL,
    ends_at          DATE            NOT NULL,
    active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at       TIMESTAMP       NULL,
    updated_at       TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY promotions_hotel_id_starts_at_ends_at_index (hotel_id, starts_at, ends_at),
    CONSTRAINT promotions_hotel_id_foreign FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
    CONSTRAINT promotions_room_id_foreign FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reserves (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(12)     NULL COMMENT 'Localizador da reserva (ex.: FH7K3Q9X), usado em "minha reserva"',
    hotel_id      BIGINT UNSIGNED NOT NULL,
    room_id       BIGINT UNSIGNED NOT NULL,
    coupon_id     BIGINT UNSIGNED NULL,
    external_code VARCHAR(50)     NULL COMMENT 'Código da reserva no sistema de origem (XML)',
    check_in      DATE            NOT NULL,
    check_out     DATE            NOT NULL,
    subtotal      DECIMAL(10,2)   NOT NULL DEFAULT 0 COMMENT 'Soma das diárias',
    discount      DECIMAL(10,2)   NOT NULL DEFAULT 0 COMMENT 'Promoções + cupom',
    fees          DECIMAL(10,2)   NOT NULL DEFAULT 0 COMMENT 'Taxas de serviço',
    total         DECIMAL(10,2)   NOT NULL,
    status        VARCHAR(20)     NOT NULL DEFAULT 'pending' COMMENT 'pending | partially_paid | paid | cancelled',
    source        VARCHAR(20)     NOT NULL DEFAULT 'api' COMMENT 'api | xml',
    expires_at    TIMESTAMP       NULL COMMENT 'Pré-reserva online sem pagamento: deixa de ocupar o quarto após este momento',
    created_at    TIMESTAMP       NULL,
    updated_at    TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY reserves_code_unique (code),
    UNIQUE KEY reserves_external_code_unique (external_code),
    KEY reserves_room_id_check_in_check_out_index (room_id, check_in, check_out),
    KEY reserves_status_index (status),
    KEY reserves_status_expires_at_index (status, expires_at),
    KEY reserves_hotel_id_check_in_index (hotel_id, check_in),
    CONSTRAINT reserves_hotel_id_foreign  FOREIGN KEY (hotel_id)  REFERENCES hotels (id)  ON DELETE RESTRICT,
    CONSTRAINT reserves_room_id_foreign   FOREIGN KEY (room_id)   REFERENCES rooms (id)   ON DELETE RESTRICT,
    CONSTRAINT reserves_coupon_id_foreign FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE guest_reserve (
    reserve_id BIGINT UNSIGNED NOT NULL,
    guest_id   BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (reserve_id, guest_id),
    CONSTRAINT guest_reserve_reserve_id_foreign FOREIGN KEY (reserve_id) REFERENCES reserves (id) ON DELETE CASCADE,
    CONSTRAINT guest_reserve_guest_id_foreign   FOREIGN KEY (guest_id)   REFERENCES guests (id)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dailies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reserve_id BIGINT UNSIGNED NOT NULL,
    date       DATE            NOT NULL,
    value      DECIMAL(10,2)   NOT NULL COMMENT 'Valor bruto da diária',
    discount   DECIMAL(10,2)   NOT NULL DEFAULT 0 COMMENT 'Desconto da diária (promoção + parte proporcional do cupom/desconto da reserva)',
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY dailies_reserve_id_date_unique (reserve_id, date),
    KEY dailies_date_reserve_id_index (date, reserve_id),
    CONSTRAINT dailies_reserve_id_foreign FOREIGN KEY (reserve_id) REFERENCES reserves (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    reserve_id   BIGINT UNSIGNED  NOT NULL,
    method       TINYINT UNSIGNED NOT NULL COMMENT '1 crédito | 2 débito | 3 pix | 4 dinheiro | 5 boleto',
    value        DECIMAL(10,2)    NOT NULL COMMENT 'Valor abatido do saldo da reserva',
    installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
    interest     DECIMAL(10,2)    NOT NULL DEFAULT 0 COMMENT 'Juros de parcelamento cobrados do hóspede',
    source       VARCHAR(20)      NOT NULL DEFAULT 'api' COMMENT 'api | xml',
    paid_at      TIMESTAMP        NULL,
    refunded_at  TIMESTAMP        NULL COMMENT 'Estorno: o pagamento deixa de abater o saldo da reserva',
    refunded_by  BIGINT UNSIGNED  NULL,
    created_at   TIMESTAMP        NULL,
    updated_at   TIMESTAMP        NULL,
    PRIMARY KEY (id),
    CONSTRAINT payments_reserve_id_foreign  FOREIGN KEY (reserve_id)  REFERENCES reserves (id) ON DELETE CASCADE,
    CONSTRAINT payments_refunded_by_foreign FOREIGN KEY (refunded_by) REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB;
