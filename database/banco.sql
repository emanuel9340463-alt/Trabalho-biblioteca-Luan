CREATE DATABASE IF NOT EXISTS biblioteca_luan;
USE biblioteca_luan;

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

CREATE TABLE IF NOT EXISTS emprestimos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    livro_id INT NOT NULL,
    data_emprestimo DATE NOT NULL,
    data_devolucao DATE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id),
    FOREIGN KEY (livro_id) REFERENCES livros(id)
);

INSERT INTO emprestimos (aluno_id, livro_id, data_emprestimo, data_devolucao)
VALUES(1, 5,  CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY));

ALTER TABLE emprestimos
ADD COLUMN data_devolucao_real DATE NULL;

SELECT * FROM livros;
SELECT * FROM alunos;
SELECT * FROM emprestimos;