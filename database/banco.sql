-- Cria o banco usado pela aplicação e o seleciona para os próximos comandos.
CREATE DATABASE IF NOT EXISTS biblioteca_luan;
USE biblioteca_luan;

-- Armazena os dados dos leitores cadastrados.
CREATE TABLE IF NOT EXISTS alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data_nascimento DATE NOT NULL,
    turma VARCHAR(5) NOT NULL
);

CREATE TABLE IF NOT EXISTS livros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    isbn VARCHAR(100) UNIQUE NOT NULL,
    genero VARCHAR(50) NOT NULL,
    codigo_bibli VARCHAR(20) UNIQUE NOT NULL,
    status_livro VARCHAR(20) DEFAULT 'disponivel',
    quantidade INT UNSIGNED NOT NULL DEFAULT 1
);

-- Compatibilização: adiciona quantidade caso o banco seja de uma versão anterior.
SET @coluna_quantidade_existe = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'livros'
      AND column_name = 'quantidade'
);
SET @migracao_quantidade = IF(
    @coluna_quantidade_existe = 0,
    'ALTER TABLE livros ADD quantidade INT UNSIGNED NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE stmt_migracao_quantidade FROM @migracao_quantidade;
EXECUTE stmt_migracao_quantidade;
DEALLOCATE PREPARE stmt_migracao_quantidade;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL
);

-- Guarda somente identificadores hash para limitar tentativas de login.
CREATE TABLE IF NOT EXISTS login_tentativas (
    chave_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    falhas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultima_tentativa DATETIME NOT NULL,
    bloqueado_ate DATETIME NULL,
    INDEX idx_login_tentativas_data (ultima_tentativa)
);

-- Cada linha representa uma retirada registrada no sistema.
CREATE TABLE IF NOT EXISTS emprestimos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    livro_id INT NOT NULL,
    data_emprestimo DATE NOT NULL,
    data_devolucao DATE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id),
    FOREIGN KEY (livro_id) REFERENCES livros(id)
);

-- Adiciona a data real de devolução em bancos anteriores sem remover registros.
SET @coluna_devolucao_real_existe = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'emprestimos'
      AND column_name = 'data_devolucao_real'
);
SET @migracao_devolucao_real = IF(
    @coluna_devolucao_real_existe = 0,
    'ALTER TABLE emprestimos ADD COLUMN data_devolucao_real DATE NULL',
    'SELECT 1'
);
PREPARE stmt_migracao_devolucao_real FROM @migracao_devolucao_real;
EXECUTE stmt_migracao_devolucao_real;
DEALLOCATE PREPARE stmt_migracao_devolucao_real;

INSERT INTO biblioteca_luan.usuarios (email, senha)
VALUES ('admin@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$eEcvaWphMDFvQmcvbmtQTw$hTlxyCCC9s1ckjl/1nI+6TJ9U8582bhpnKQIETBrcdk');