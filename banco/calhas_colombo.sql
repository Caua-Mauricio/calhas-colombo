-- banco de dados do sistema calhas colombo
-- import isso no phpmyadmin, aba Importar

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `calhas_colombo`;
CREATE DATABASE `calhas_colombo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `calhas_colombo`;

-- tabela usuarios
CREATE TABLE `usuarios` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `senha`         VARCHAR(255) NOT NULL,
  `perfil`        ENUM('admin','gerente','calheiro','campo') NOT NULL DEFAULT 'campo',
  `telefone`      VARCHAR(20) DEFAULT NULL,
  `ativo`         TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_acesso` DATETIME DEFAULT NULL,
  `data_cadastro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_perfil` (`perfil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tabela clientes
CREATE TABLE `clientes` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `nome`             VARCHAR(100) NOT NULL,
  `tipo_pessoa`      ENUM('fisica','juridica') NOT NULL DEFAULT 'fisica',
  `cpf_cnpj`         VARCHAR(20) DEFAULT NULL,
  `telefone`         VARCHAR(20) NOT NULL,
  `whatsapp`         VARCHAR(20) DEFAULT NULL,
  `email`            VARCHAR(150) DEFAULT NULL,
  `cep`              VARCHAR(10) DEFAULT NULL,
  `endereco`         VARCHAR(150) NOT NULL,
  `numero`           VARCHAR(15) DEFAULT NULL,
  `complemento`      VARCHAR(100) DEFAULT NULL,
  `bairro`           VARCHAR(60) DEFAULT NULL,
  `cidade`           VARCHAR(60) DEFAULT NULL,
  `uf`               CHAR(2) DEFAULT 'SC',
  `ponto_referencia` VARCHAR(150) DEFAULT NULL,
  `origem`           ENUM('indicacao','instagram','google','fachada','outro') DEFAULT 'outro',
  `ativo`            TINYINT(1) NOT NULL DEFAULT 1,
  `observacoes`      TEXT DEFAULT NULL,
  `data_cadastro`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nome` (`nome`),
  KEY `idx_cpf_cnpj` (`cpf_cnpj`),
  KEY `idx_cidade` (`cidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tabela fornecedores
CREATE TABLE `fornecedores` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(150) NOT NULL,
  `cnpj`          VARCHAR(20) DEFAULT NULL,
  `telefone`      VARCHAR(20) DEFAULT NULL,
  `email`         VARCHAR(150) DEFAULT NULL,
  `cidade`        VARCHAR(60) DEFAULT NULL,
  `uf`            CHAR(2) DEFAULT NULL,
  `ativo`         TINYINT(1) NOT NULL DEFAULT 1,
  `observacoes`   TEXT DEFAULT NULL,
  `data_cadastro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PRODUTOS  (com controle de estoque e custo)
-- ---------------------------------------------------------------------
CREATE TABLE `produtos` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `nome`           VARCHAR(100) NOT NULL,
  `categoria`      VARCHAR(50) DEFAULT 'Geral',
  `unidade`        ENUM('metro','kg','un','peca','rolo') NOT NULL DEFAULT 'un',
  `preco_custo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `preco_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estoque_atual`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estoque_minimo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `ativo`          TINYINT(1) NOT NULL DEFAULT 1,
  `data_cadastro`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tabela orcamentos
CREATE TABLE `orcamentos` (
  `id`                    INT(11) NOT NULL AUTO_INCREMENT,
  `numero`                VARCHAR(20) NOT NULL,
  `token`                 CHAR(32) NOT NULL,
  `cliente_id`            INT(11) NOT NULL,
  `responsavel_id`        INT(11) NOT NULL,
  `data_orcamento`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_entrega_prevista` DATE DEFAULT NULL,
  `validade_dias`         INT(11) NOT NULL DEFAULT 15,
  `status`                ENUM('rascunho','enviado','aprovado','em_producao','concluido','cancelado') NOT NULL DEFAULT 'rascunho',
  `tipo_telhado`          VARCHAR(50) DEFAULT NULL,
  `corte_chapa`           VARCHAR(30) DEFAULT NULL,
  `condicao_acesso`       VARCHAR(50) DEFAULT NULL,
  `acabamento_cor`        VARCHAR(50) DEFAULT NULL,
  `inclui_rufos`          TINYINT(1) NOT NULL DEFAULT 0,
  `inclui_condutores`     TINYINT(1) NOT NULL DEFAULT 0,
  `inclui_veda_calha`     TINYINT(1) NOT NULL DEFAULT 0,
  `inclui_cabeceiras`     TINYINT(1) NOT NULL DEFAULT 0,
  `valor_materiais`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_mao_obra`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `percentual_desconto`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `valor_desconto`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_total`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `forma_pagamento`       VARCHAR(40) DEFAULT NULL,
  `observacoes`           TEXT DEFAULT NULL,
  `data_resposta`         DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero` (`numero`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_responsavel` (`responsavel_id`),
  KEY `idx_status` (`status`),
  KEY `idx_data` (`data_orcamento`),
  CONSTRAINT `fk_orc_cliente`     FOREIGN KEY (`cliente_id`)     REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_orc_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ITENS DO ORCAMENTO
-- ---------------------------------------------------------------------
CREATE TABLE `itens_orcamento` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `orcamento_id`   INT(11) NOT NULL,
  `produto_id`     INT(11) NOT NULL,
  `quantidade`     DECIMAL(10,2) NOT NULL,
  `valor_unitario` DECIMAL(10,2) NOT NULL,
  `valor_total`    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_item_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_produto`   FOREIGN KEY (`produto_id`)   REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MEDIDAS DE CAMPO
-- ---------------------------------------------------------------------
CREATE TABLE `medidas` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `orcamento_id` INT(11) NOT NULL,
  `tipo`         VARCHAR(50) NOT NULL,
  `valor`        DECIMAL(10,2) NOT NULL,
  `unidade`      VARCHAR(10) NOT NULL DEFAULT 'm',
  `observacao`   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  CONSTRAINT `fk_medida_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- COMPRAS DE INSUMOS  (alimenta o estoque)
-- ---------------------------------------------------------------------
CREATE TABLE `compras_insumos` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `descricao`      VARCHAR(255) NOT NULL,
  `produto_id`     INT(11) DEFAULT NULL,
  `fornecedor_id`  INT(11) DEFAULT NULL,
  `quantidade`     DECIMAL(10,2) NOT NULL,
  `unidade`        VARCHAR(20) NOT NULL DEFAULT 'un',
  `valor_total`    DECIMAL(10,2) NOT NULL,
  `data_compra`    DATE NOT NULL,
  `usuario_id`     INT(11) DEFAULT NULL,
  `data_registro`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_data` (`data_compra`),
  KEY `idx_produto` (`produto_id`),
  KEY `idx_fornecedor` (`fornecedor_id`),
  CONSTRAINT `fk_compra_produto`    FOREIGN KEY (`produto_id`)    REFERENCES `produtos` (`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_compra_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  DADOS INICIAIS
--  Senhas ja criptografadas com BCRYPT (password_hash do PHP)
--  admin@colombo.com   -> admin123
--  gerente@colombo.com -> gerente123
--  caua@colombo.com    -> 123456
--  pedro@colombo.com   -> 654321

INSERT INTO `usuarios` (`id`,`nome`,`email`,`senha`,`perfil`,`telefone`,`ativo`,`data_cadastro`) VALUES
(1,'Chefe Colombo','admin@colombo.com','$2a$10$8Khfu1nFrGwmmJaRv0EwFOoDK/f3D9MRJcStLVqTibySsw7U6Bc9.','admin','48000000000',1,'2026-07-20 09:00:00'),
(2,'Gerente Vendas','gerente@colombo.com','$2a$10$HeVrzsZD/vaXiL4dJuGj/e8v2MQsd7VCXlFpu3Cxzzc6GBK20xlOm','gerente','48988888888',1,'2026-07-20 09:10:00'),
(3,'Caua Mauricio','caua@colombo.com','$2a$10$J8njMMPWQhxgHCJDsTB8l.OGp6TQsuapKb2J1ymJFfRrbpS./lErS','campo','48999999999',1,'2026-07-26 00:21:07'),
(4,'Pedro Renato','pedro@colombo.com','$2a$10$VIRleujJb7GdErd0Fyskn.Dse40kSHfYpnQ2IF4grt92uSUDIbDD.','calheiro','48676999888',1,'2026-08-19 21:54:42');

INSERT INTO `clientes` (`id`,`nome`,`tipo_pessoa`,`cpf_cnpj`,`telefone`,`whatsapp`,`email`,`cep`,`endereco`,`numero`,`complemento`,`bairro`,`cidade`,`uf`,`ponto_referencia`,`origem`,`observacoes`,`data_cadastro`) VALUES
(1,'Cida Carros','fisica','120.120.120-64','48999888777','48999888777','cida@email.com','88817-540','Rua das Flores','123','Casa','Vila Nova','Criciuma','SC','Ao lado do mercado','indicacao','Cachorro bravo no quintal','2026-08-24 15:06:38'),
(2,'Construtora Horizonte LTDA','juridica','12.345.678/0001-90','4833334444','4899665544','contato@horizonte.com.br','88801-000','Avenida Centenario','1500','Sala 3','Centro','Criciuma','SC','Predio azul','google','Cliente recorrente - obras residenciais','2026-08-25 10:30:00'),
(3,'Marcos Pereira','fisica','987.654.321-00','48991234567','48991234567',NULL,'88804-100','Rua Sao Jose','88',NULL,'Prospera','Criciuma','SC',NULL,'instagram',NULL,'2026-09-01 08:15:00');

INSERT INTO `fornecedores` (`id`,`nome`,`cnpj`,`telefone`,`email`,`cidade`,`uf`,`ativo`) VALUES
(1,'Cri Chapa Metais','98.765.432/0001-00','4899999999','vendas@crichapa.com.br','Criciuma','SC',1),
(2,'Aluminios do Sul','11.222.333/0001-80','4833221100','comercial@aluminiosul.com.br','Tubarao','SC',1);

INSERT INTO `produtos` (`id`,`nome`,`categoria`,`unidade`,`preco_custo`,`preco_unitario`,`estoque_atual`,`estoque_minimo`,`ativo`) VALUES
(1,'Calha de Aluminio 0,5mm','Calhas','metro',25.00,45.00,120.00,50.00,1),
(2,'Parafuso de Fixacao','Fixacao','un',0.15,0.50,480.00,100.00,1),
(3,'Veda Calha (Tubo)','Vedacao','un',8.00,18.90,14.00,20.00,1),
(4,'Suporte para Calha','Fixacao','peca',6.50,12.50,90.00,30.00,1),
(5,'Pingadeira','Acabamento','un',3.00,8.00,55.00,15.00,1),
(6,'Corrente Condutora','Acabamento','metro',4.00,10.00,8.00,20.00,1),
(7,'Rufo Galvanizado','Acabamento','metro',18.00,32.00,60.00,25.00,1),
(8,'Condutor Retangular','Condutores','metro',20.00,38.00,45.00,20.00,1);

INSERT INTO `orcamentos` (`id`,`numero`,`token`,`cliente_id`,`responsavel_id`,`data_orcamento`,`data_entrega_prevista`,`validade_dias`,`status`,`tipo_telhado`,`corte_chapa`,`condicao_acesso`,`acabamento_cor`,`inclui_rufos`,`inclui_condutores`,`inclui_veda_calha`,`inclui_cabeceiras`,`valor_materiais`,`valor_mao_obra`,`percentual_desconto`,`valor_desconto`,`valor_total`,`forma_pagamento`,`observacoes`) VALUES
(1,'ORC-2026-0001','a1b2c3d4e5f60718293a4b5c6d7e8f90',1,3,'2026-08-24 17:48:24','2026-10-23',15,'aprovado','Telha Ceramica','Corte 33','Escada Simples','Natural',1,1,1,0,574.00,150.00,0.00,0.00,724.00,'Pix','Entrada de 50% e saldo na conclusao.'),
(2,'ORC-2026-0002','b2c3d4e5f60718293a4b5c6d7e8f9011',2,4,'2026-09-02 10:12:00','2026-10-05',20,'enviado','Fibrocimento','Corte 40','Andaime','Branco',1,1,1,1,1860.00,450.00,5.00,115.50,2194.50,'Cartao de Credito','Obra com 3 pavimentos. Acesso restrito pela manha.'),
(3,'ORC-2026-0003','c3d4e5f60718293a4b5c6d7e8f901122',3,3,'2026-09-08 14:30:00','2026-09-30',15,'rascunho','Telha Americana',NULL,'Escada Simples','Natural',0,1,1,0,392.00,180.00,0.00,0.00,572.00,'Dinheiro',NULL);

INSERT INTO `itens_orcamento` (`orcamento_id`,`produto_id`,`quantidade`,`valor_unitario`,`valor_total`) VALUES
(1,1,12.00,42.00,504.00),
(1,4,4.00,12.50,50.00),
(1,2,16.00,0.50,8.00),
(1,5,2.00,6.00,12.00),
(2,1,30.00,45.00,1350.00),
(2,7,10.00,32.00,320.00),
(2,4,12.00,12.50,150.00),
(2,3,2.00,20.00,40.00),
(3,1,8.00,44.00,352.00),
(3,4,2.00,12.00,24.00),
(3,5,2.00,8.00,16.00);

INSERT INTO `medidas` (`orcamento_id`,`tipo`,`valor`,`unidade`,`observacao`) VALUES
(1,'Calha frontal',8.50,'m','Face voltada para a rua'),
(1,'Calha lateral',3.50,'m',NULL),
(2,'Calha frontal',18.00,'m','Fachada principal'),
(2,'Calha fundos',12.00,'m',NULL),
(2,'Altura do condutor',9.00,'m','Tres pavimentos');

INSERT INTO `compras_insumos` (`descricao`,`produto_id`,`fornecedor_id`,`quantidade`,`unidade`,`valor_total`,`data_compra`,`usuario_id`) VALUES
('Bobina Chapa 0,5mm',1,1,50.00,'metro',1250.00,'2026-08-24',1),
('Lote de suportes',4,2,100.00,'peca',650.00,'2026-08-28',1),
('Tubos de veda calha',3,1,20.00,'un',160.00,'2026-09-03',2);
