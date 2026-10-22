fornecedoresCREATE DATABASE biblioteca_luan;
USE biblioteca_luan;

CREATE TABLE alunos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data_nascimento DATE NOT NULL,
    turma VARCHAR(5) NOT NULL
);

CREATE TABLE livros (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    isbn VARCHAR(100) UNIQUE NOT NULL,
    genero VARCHAR(50) NOT NULL,
    codigo_bibli VARCHAR(20) UNIQUE NOT NULL,
    status_livro VARCHAR(20) DEFAULT 'disponivel'
);

SELECT * FROM livros;
SELECT * FROM alunos;

CREATE TABLE emprestimos (
    id SERIAL PRIMARY KEY,
    aluno_id INT NOT NULL,
    livro_id INT NOT NULL,
    data_emprestimo DATE NOT NULL,
    data_devolucao DATE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id),
    FOREIGN KEY (livro_id) REFERENCES livros(id)
);