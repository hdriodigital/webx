<?php
/*
Plugin Name: Agendamento GDC
Description: Um plugin para cadastro de clientes com funcionalidades específicas.
Version: 1.1
Author: Jose Luiz Cordeiro
*/

// modifica 
// // Modifique a função criar_tabelas() para incluir a coluna descricao
function criar_tabelas() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name_clientes = $wpdb->prefix . 'cliente_cadastro';
    $sql_clientes = "CREATE TABLE $table_name_clientes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome_responsavel tinytext NOT NULL,
        nome_estabelecimento tinytext NOT NULL,
        cnpj_cpf varchar(14) NOT NULL,
        telefone varchar(15) NOT NULL,
        whatsapp varchar(15),
        endereco text NOT NULL,
        desconto varchar(10),
        data_cadastro datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $table_name_agendamentos = $wpdb->prefix . 'agendamentos';
    $sql_agendamentos = "CREATE TABLE $table_name_agendamentos (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        cliente_id mediumint(9) NOT NULL,
        dia date NOT NULL,
        hora time NOT NULL,
        tipo_servico varchar(50) NOT NULL,
        valor decimal(10,2) NOT NULL,
        descricao text,
        status varchar(20) NOT NULL DEFAULT 'agendado',
        data_criacao datetime NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (cliente_id) REFERENCES {$table_name_clientes}(id) ON DELETE CASCADE
    ) $charset_collate;";


function criar_tabela_limite_usuarios() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'limite_agendamentos';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        limite_mensal int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";

    dbDelta($sql);
}
register_activation_hook(__FILE__, 'criar_tabela_limite_usuarios');




    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_clientes);
    dbDelta($sql_agendamentos);
}


// Adicione esta função para atualizar a tabela se necessário
function atualizar_tabelas() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name_agendamentos = $wpdb->prefix . 'agendamentos';
    
    // Verifica se a coluna descricao existe
    $columns = $wpdb->get_col("DESCRIBE $table_name_agendamentos", 0);
    if (!in_array('descricao', $columns)) {
        $wpdb->query("ALTER TABLE $table_name_agendamentos ADD descricao text;");
    }
}

// Execute a atualização após a ativação
register_activation_hook(__FILE__, 'atualizar_tabelas');

register_activation_hook(__FILE__, 'cliente_cadastro_plugin_activate');



// Função para exibir o formulário de cadastro
function cliente_cadastro_form() {
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar logado para cadastrar um cliente.</p>';
    }

    ob_start();

    if (isset($_GET['cadastro'])) {
        $msg = ($_GET['cadastro'] == 'sucesso') 
            ? 'Cadastro realizado com sucesso!' 
            : 'Erro ao salvar os dados do cliente. Por favor, tente novamente.';
        $class = ($_GET['cadastro'] == 'sucesso') ? 'success' : 'error';
        echo "<div class=\"notice notice-$class is-dismissible\"><p>$msg</p></div>";
    }
    
    ?>
    <form action="" method="post">
        <label for="nome_responsavel">Nome do Responsável ou Razão Social:</label>
        <input type="text" name="nome_responsavel" required><br>

        <label for="nome_estabelecimento">Nome do Estabelecimento:</label>
        <input type="text" name="nome_estabelecimento" required><br>

        <label for="cnpj_cpf">CNPJ/CPF:</label>
        <input type="text" name="cnpj_cpf" required><br>

        <label for="telefone">Telefone:</label>
        <input type="text" name="telefone" required><br>

        <label for="whatsapp">WhatsApp:</label>
        <input type="text" name="whatsapp"><br>

        <label for="endereco">Endereço:</label>
        <textarea name="endereco" required></textarea><br>

        <label for="desconto">Desconto (%):</label>
        <input type="number" name="desconto" min="0" max="100" step="0.1"><br>

        <input type="submit" name="submit_cliente_cadastro" value="Cadastrar">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('cliente_cadastro_form', 'cliente_cadastro_form');

// Função para processar o cadastro de clientes
function process_cliente_cadastro() {
    if (isset($_POST['submit_cliente_cadastro']) && is_user_logged_in()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cliente_cadastro';

        // Sanitizar os dados
        $data = [
            'nome_responsavel' => sanitize_text_field($_POST['nome_responsavel']),
            'nome_estabelecimento' => sanitize_text_field($_POST['nome_estabelecimento']),
            'cnpj_cpf' => sanitize_text_field($_POST['cnpj_cpf']),
            'telefone' => sanitize_text_field($_POST['telefone']),
            'whatsapp' => sanitize_text_field($_POST['whatsapp']),
            'endereco' => sanitize_textarea_field($_POST['endereco']),
            'desconto' => is_numeric($_POST['desconto']) ? sanitize_text_field($_POST['desconto']) : '0',
            'data_cadastro' => current_time('mysql')
        ];

        // Inserir dados no banco de dados
        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            $redirect_url = add_query_arg('cadastro', 'sucesso', $_SERVER['REQUEST_URI']);
            wp_redirect($redirect_url);
            exit;
        } else {
            $redirect_url = add_query_arg('cadastro', 'erro', $_SERVER['REQUEST_URI']);
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('init', 'process_cliente_cadastro');

//AGENDAMENTO

function agendar_cliente() {
    global $wpdb;

    // Verifica se o usuário está logado
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar logado para acessar este conteúdo.</p>';
    }

    // Inicializar variáveis
    $mensagem = '';
    $cliente_cadastrado = true; // Variável para verificar se o cliente está cadastrado

    if (isset($_POST['agendar'])) {
        try {
            $cliente_nome = sanitize_text_field($_POST['cliente_nome']);
            $dia = sanitize_text_field($_POST['dia']);
            $hora = sanitize_text_field($_POST['hora']);
            $tipo_servico = sanitize_text_field($_POST['tipo_servico']);
            $valor = sanitize_text_field($_POST['valor']);
            $descricao = sanitize_textarea_field($_POST['descricao']);

            // Buscar o cliente pelo nome
            $cliente = $wpdb->get_row($wpdb->prepare("
                SELECT id 
                FROM {$wpdb->prefix}cliente_cadastro 
                WHERE nome_responsavel = %s", 
                $cliente_nome
            ));

            if (!$cliente) {
                throw new Exception('Cliente não encontrado');
            }

            $cliente_id = $cliente->id;
            
            // Se o cliente existir, prosseguir com o agendamento
            $agendamento = array(
                'cliente_id' => $cliente_id,
                'dia' => $dia,
                'hora' => $hora,
                'tipo_servico' => $tipo_servico,
                'valor' => $valor,
                'descricao' => $descricao
            );

            $insert_result = $wpdb->insert(
    $table_name_agendamentos,
    [
        'user_id' => $user_id,
        'cliente_id' => $cliente_id,
        'dia' => $dia,
        'hora' => $hora,
        'tipo_servico' => $tipo_servico,
        'valor' => $valor,
        'descricao' => $descricao,
        'data_criacao' => current_time('mysql'),
    ],
    ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s']
);


            if ($insert_result === false) {
                throw new Exception('Erro ao inserir agendamento: ' . $wpdb->last_error);
            }

            $mensagem = '<div class="notice notice-success">Agendamento realizado com sucesso!</div>';

        } catch (Exception $e) {
            $mensagem = '<div class="notice notice-error">' . esc_html($e->getMessage()) . '</div>';
        }
    }
	

    // Criar formulário de agendamento
    ?>
    <?php if (!empty($mensagem)) echo $mensagem; ?> 
    <form method="post">
        <label for="cliente_nome">Nome do Cliente:</label>
        <input type="text" id="cliente_nome" name="cliente_nome" required placeholder="Digite o nome do cliente"><br>

        <label for="dia">Dia:</label>
        <input type="date" id="dia" name="dia" required><br>

        <label for="hora">Hora:</label>
        <input type="time" id="hora" name="hora" required><br>

        <label for="tipo_servico">Tipo de serviço:</label>
        <input type="text" id="tipo_servico" name="tipo_servico" required><br>

        <label for="valor">Valor:</label>
        <input type="number" id="valor" name="valor" step="0.01" required><br>

        <label for="descricao">Descrição:</label>
        <textarea id="descricao" name="descricao"></textarea><br>

        <input type="submit" name="agendar" value="Agendar">
    </form>
    <?php
}

add_shortcode('agendar_servico', 'agendar_cliente');

// Função para exibir a pesquisa de clientes
function pesquisar_clientes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cliente_cadastro';

    $clientes = null; // Inicia variável para armazenar os clientes
    $mensagem = '';

    // Verifica se o formulário foi enviado e se o campo não está vazio
    if (isset($_POST['search_cliente']) && !empty(trim($_POST['search_cliente']))) {
        $search_cliente = sanitize_text_field($_POST['search_cliente']);
        $clientes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE nome_responsavel LIKE %s", '%' . $wpdb->esc_like($search_cliente) . '%'));

        if (empty($clientes)) {
            $mensagem = 'Nenhum cliente encontrado com o nome "' . esc_html($search_cliente) . '".';
        }
    }

    // Verifica se o formulário de edição foi enviado
    if (isset($_POST['editar_cliente'])) {
        $cliente_id = intval($_POST['cliente_id']);
        $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cliente_id));
    }

    // Verifica se o formulário de salvar foi enviado
    if (isset($_POST['salvar_cliente'])) {
        try {
            $cliente_id = intval($_POST['cliente_id']);
            $nome_responsavel = sanitize_text_field($_POST['nome_responsavel']);
            $nome_estabelecimento = sanitize_text_field($_POST['nome_estabelecimento']);
            $cnpj_cpf = sanitize_text_field($_POST['cnpj_cpf']);
            $telefone = sanitize_text_field($_POST['telefone']);
            $whatsapp = sanitize_text_field($_POST['whatsapp']);
            $endereco = sanitize_text_field($_POST['endereco']);
            $desconto = is_numeric($_POST['desconto']) ? sanitize_text_field($_POST['desconto']) : '0';

            // Atualiza o cliente no banco de dados
            $wpdb->update(
                $table_name,
                array(
                    'nome_responsavel' => $nome_responsavel,
                    'nome_estabelecimento' => $nome_estabelecimento,
                    'cnpj_cpf' => $cnpj_cpf,
                    'telefone' => $telefone,
                    'whatsapp' => $whatsapp,
                    'endereco' => $endereco,
                    'desconto' => $desconto,
                ),
                array('id' => $cliente_id)
            );

            if ($wpdb->rows_affected === 0) {
                throw new Exception('Nenhuma alteração detectada');
            }

            $mensagem = 'Cliente atualizado com sucesso!';
        } catch (Exception $e) {
            $mensagem = 'Erro ao atualizar cliente: ' . esc_html($e->getMessage());
        }
    }

    ob_start();
    ?>
    <form method="post">
        <label for="search_cliente">Pesquisar Cliente:</label>
        <input type="text" name="search_cliente" id="search_cliente" placeholder="Digite o nome do cliente" value="<?php echo isset($_POST['search_cliente']) ? esc_attr($_POST['search_cliente']) : ''; ?>">
        <input type="submit" value="Pesquisar">
    </form>

    <?php if (isset($clientes) && !empty($clientes)): ?>
        <h2>Resultados da Pesquisa:</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome do Responsável</th>
                    <th>Nome do Estabelecimento</th>
                    <th>CNPJ/CPF</th>
                    <th>Telefone</th>
                    <th>WhatsApp</th>
                    <th>Endereço</th>
                    <th>Desconto</th>
                    <th>Data de Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?php echo esc_html($cliente->nome_responsavel); ?></td>
                        <td><?php echo esc_html($cliente->nome_estabelecimento); ?></td>
                        <td><?php echo esc_html($cliente->cnpj_cpf); ?></td>
                        <td><?php echo esc_html($cliente->telefone); ?></td>
                        <td><?php echo esc_html($cliente->whatsapp); ?></td>
                        <td><?php echo esc_html($cliente->endereco); ?></td>
                        <td><?php echo esc_html($cliente->desconto); ?></td>
                        <td><?php echo esc_html($cliente->data_cadastro); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="cliente_id" value="<?php echo esc_attr($cliente->id); ?>">
                                <input type="submit" name="editar_cliente" value="Editar">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (isset($cliente)): ?>
        <h2>Editar Cliente</h2>
        <form method="post">
            <input type="hidden" name="cliente_id" value="<?php echo esc_attr($cliente->id); ?>">
            
            <label for="nome_responsavel">Nome do Responsável:</label>
            <input type="text" name="nome_responsavel" value="<?php echo esc_attr($cliente->nome_responsavel); ?>" required><br>

            <label for="nome_estabelecimento">Nome do Estabelecimento:</label>
            <input type="text" name="nome_estabelecimento" value="<?php echo esc_attr($cliente->nome_estabelecimento); ?>" required><br>

            <label for="cnpj_cpf">CNPJ/CPF:</label>
            <input type="text" name="cnpj_cpf" value="<?php echo esc_attr($cliente->cnpj_cpf); ?>" required><br>

            <label for="telefone">Telefone:</label>
            <input type="text" name="telefone" value="<?php echo esc_attr($cliente->telefone); ?>" required><br>

            <label for="whatsapp">WhatsApp:</label>
            <input type="text" name="whatsapp" value="<?php echo esc_attr($cliente->whatsapp); ?>"><br>

            <label for="endereco">Endereço:</label>
            <textarea name="endereco"><?php echo esc_html($cliente->endereco); ?></textarea><br>

            <label for="desconto">Desconto (%):</label>
            <input type="number" name="desconto" value="<?php echo esc_attr($cliente->desconto); ?>" min="0" max="100" step="0.1"><br>

            <input type="submit" name="salvar_cliente" value="Salvar Alterações">
        </form>
    <?php endif; ?>

    <?php if (!empty($mensagem)) echo "<div class=\"notice notice-info\">$mensagem</div>"; ?>
    <?php
    return ob_get_clean();
}

// Registro do shortcode
add_shortcode('pesquisar_clientes', 'pesquisar_clientes');

// Função para limpar dados ao desativar o plugin
function cliente_cadastro_plugin_desactivate() {
    global $wpdb;
    $table_name_clientes = $wpdb->prefix . 'cliente_cadastro';
    $table_name_agendamentos = $wpdb->prefix . 'agendamentos';

    // Excluir as tabelas do banco de dados
    $wpdb->query("DROP TABLE IF EXISTS $table_name_clientes");
    $wpdb->query("DROP TABLE IF EXISTS $table_name_agendamentos");
}

// Registro da função de desativação
register_deactivation_hook(__FILE__, 'cliente_cadastro_plugin_desactivate');

// Função para ativar o plugin
function cliente_cadastro_plugin_activate() {
    criar_tabelas();
}

// Função para verificar dependências no carregamento do plugin
function cliente_cadastro_plugin_load() {
    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
}
add_action('plugins_loaded', 'cliente_cadastro_plugin_load');

//Função relatorio agendamento
function relatorio_servicos() {
    global $wpdb;

    ob_start();

    $table_name_clientes = $wpdb->prefix . 'cliente_cadastro';
    $table_name_agendamentos = $wpdb->prefix . 'agendamentos';
    $mensagem_sucesso = '';

    if (isset($_POST['agendamento_id'])) {
        $agendamento_id = intval($_POST['agendamento_id']);
        $dia = sanitize_text_field($_POST['dia']);
        $hora = sanitize_text_field($_POST['hora']);
        $tipo_servico = sanitize_text_field($_POST['tipo_servico']);
        $valor = floatval($_POST['valor']);
        $descricao = sanitize_textarea_field($_POST['descricao']);
        $nome_cliente = sanitize_text_field($_POST['nome_cliente']);
        $nome_empresa = sanitize_text_field($_POST['nome_empresa']);

        $wpdb->update(
            $table_name_agendamentos,
            array(
                'dia' => $dia,
                'hora' => $hora,
                'tipo_servico' => $tipo_servico,
                'valor' => $valor,
                'descricao' => $descricao
            ),
            array('id' => $agendamento_id),
            array('%s', '%s', '%s', '%f', '%s'),
            array('%d')
        );

        $mensagem_sucesso = "Agendamento editado com sucesso para $nome_cliente da empresa $nome_empresa!";
    }

    if (!empty($mensagem_sucesso)) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html($mensagem_sucesso) . '</p></div>';
    }

    if (isset($_POST['search_cliente'])) {
        $search_cliente = sanitize_text_field($_POST['search_cliente']);


		$clientes = $wpdb->get_results($wpdb->prepare(
    "SELECT id, nome_responsavel, nome_estabelecimento FROM $table_name_clientes WHERE nome_responsavel LIKE %s",
    '%' . $wpdb->esc_like($search_cliente) . '%'
));

		
		
		
        if (!empty($clientes)) {
            echo "<h2>Clientes Encontrados:</h2>";
			


            foreach ($clientes as $cliente) {        
			$nome_empresa = $cliente->nome_estabelecimento; // Aqui você pode colocar dinamicamente, se tiver
                echo "<h3>" . esc_html($cliente->nome_responsavel) . " (ID: " . esc_html($cliente->id) . ")</h3>";

                $agendamentos = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_name_agendamentos WHERE cliente_id = %d",
                    $cliente->id
                ));

                if (!empty($agendamentos)) {
                    echo "<table class='wp-list-table widefat fixed'>
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Hora</th>
                                    <th>Tipo de Serviço</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>";

                    foreach ($agendamentos as $agendamento) {
                        echo "<tr>
                                <td>" . esc_html($agendamento->dia) . "</td>
                                <td>" . esc_html($agendamento->hora) . "</td>
                                <td>" . esc_html($agendamento->tipo_servico) . "</td>
                                <td>" . esc_html($agendamento->valor) . "</td>
                                <td>" . esc_html($agendamento->descricao) . "</td>
                                <td>
                                    <button class='button editar-agendamento'
                                        data-id='" . esc_attr($agendamento->id) . "'
                                        data-dia='" . esc_attr($agendamento->dia) . "'
                                        data-hora='" . esc_attr($agendamento->hora) . "'
                                        data-tipo_servico='" . esc_attr($agendamento->tipo_servico) . "'
                                        data-valor='" . esc_attr($agendamento->valor) . "'
                                        data-descricao='" . esc_attr($agendamento->descricao) . "'
                                        data-nome_cliente='" . esc_attr($cliente->nome_responsavel) . "'
                                        data-nome_empresa='" . esc_attr($nome_empresa) . "'>
                                        Editar
                                    </button>
								<a href='https://wa.me/?text=" . rawurlencode(
    "🛍️ *Agendamento confirmado!*\n" .
    "🏢 Empresa: $nome_empresa\n" .
    "👤 Cliente: {$cliente->nome_responsavel}\n" .
    "📅 Dia: {$agendamento->dia}\n" .
    "⏰ Hora: {$agendamento->hora}\n" .
    "💼 Serviço: {$agendamento->tipo_servico}\n" .
    "💬 Descrição: {$agendamento->descricao}\n" .
    "💵 Valor: R$ " . number_format($agendamento->valor, 2, ',', '.')
) . "' target='_blank' class='button'>Enviar WhatsApp</a>


                                </td>
                              </tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p>Nenhum agendamento encontrado para este cliente.</p>";
                }
            }
        } else {
            echo "<p>Nenhum cliente encontrado com o nome pesquisado.</p>";
        }
    }

    ?>
    <form method="post">
        <label for="search_cliente">Pesquisar Cliente:</label>
        <input type="text" id="search_cliente" name="search_cliente" placeholder="Digite o nome do cliente" required>
        <input type="submit" value="Pesquisar">
    </form>

    <!-- Modal de edição -->
    <div id="modal-editar" style="display:none;">
        <div style="background:white; padding:20px; border:1px solid #ccc;">
            <h2>Editar Agendamento</h2>
            <form id="form-editar-agendamento" method="post">
                <input type="hidden" name="agendamento_id" id="agendamento_id">
                <input type="hidden" name="nome_cliente" id="nome_cliente">
                <input type="hidden" name="nome_empresa" id="nome_empresa">

                <label for="dia">Dia:</label>
                <input type="date" id="dia" name="dia" required>

                <label for="hora">Hora:</label>
                <input type="time" id="hora" name="hora" required>

                <label for="tipo_servico">Tipo de Serviço:</label>
                <input type="text" id="tipo_servico" name="tipo_servico" required>

                <label for="valor">Valor:</label>
                <input type="number" step="0.01" id="valor" name="valor" required>

                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao"></textarea>

                <input type="submit" value="Salvar">
                <button type="button" id="fechar-modal">Fechar</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.editar-agendamento').forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('agendamento_id').value = this.getAttribute('data-id');
                document.getElementById('dia').value = this.getAttribute('data-dia');
                document.getElementById('hora').value = this.getAttribute('data-hora');
                document.getElementById('tipo_servico').value = this.getAttribute('data-tipo_servico');
                document.getElementById('valor').value = this.getAttribute('data-valor');
                document.getElementById('descricao').value = this.getAttribute('data-descricao');
                document.getElementById('nome_cliente').value = this.getAttribute('data-nome_cliente');
                document.getElementById('nome_empresa').value = this.getAttribute('data-nome_empresa');

                document.getElementById('modal-editar').style.display = 'block';
            });
        });

        document.getElementById('fechar-modal').addEventListener('click', function () {
            document.getElementById('modal-editar').style.display = 'none';
        });
    </script>
    <?php

    return ob_get_clean();
}

// Shortcode para usar na página
add_shortcode('relatorio_agendamentos', 'relatorio_servicos');



// Relatorio geral
add_shortcode('relatorio_agendamentos', 'relatorio_servicos');

    ob_start();
function relatorio_cadastros_clientes() {
    global $wpdb;
	
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar logado para cadastrar um cliente.</p>';
    }

    $table_name_clientes = $wpdb->prefix . 'cliente_cadastro';
    ob_start();

    // Busca todos os clientes com os campos corretos
    $clientes = $wpdb->get_results("SELECT nome_responsavel, cnpj_cpf, data_cadastro FROM $table_name_clientes ORDER BY data_cadastro DESC");

    echo "<h2>Lista de Cadastros Realizados</h2>";

    if (!empty($clientes)) {
        echo "<table class='wp-list-table widefat fixed striped'>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>WhatsApp</th>
                        <th>Data de Cadastro</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($clientes as $cliente) {
            echo "<tr>
                    <td>" . esc_html($cliente->nome_responsavel) . "</td>
                    <td>" . esc_html($cliente->cnpj_cpf) . "</td>
                    <td>" . esc_html($cliente->whatsapp) . "</td>
                    <td>" . esc_html(date('d/m/Y H:i', strtotime($cliente->data_cadastro))) . "</td>
                  </tr>";
        }

        echo "</tbody></table>";
    } else {
        echo "<p>Nenhum cadastro encontrado.</p>";
    }

    return ob_get_clean();
}

// Shortcode para exibir os cadastros: [lista_cadastros]
add_shortcode('lista_cadastros', 'relatorio_cadastros_clientes');

// Shortcode para exibir os cadastros: [lista_cadastros]
add_shortcode('lista_cadastros', 'relatorio_cadastros_clientes');


// Excluir cliente
function process_excluir_cliente() {
    if (isset($_POST['excluir_cliente']) && is_user_logged_in()) {
        global $wpdb;
        $cliente_id = intval($_POST['cliente_id']);
        $table_name = $wpdb->prefix . 'cliente_cadastro';

        // Tenta excluir o cliente
        $result = $wpdb->delete($table_name, ['id' => $cliente_id]);

        if ($result === false) {
            error_log('Erro ao excluir cliente: ' . $wpdb->last_error);
            wp_redirect(add_query_arg('excluir', 'erro', $_SERVER['REQUEST_URI']));
        } else {
            wp_redirect(add_query_arg('excluir', 'sucesso', $_SERVER['REQUEST_URI']));
        }
        exit;
    }
}
add_action('init', 'process_excluir_cliente');

// Função para excluir clientes
function excluir_clientes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cliente_cadastro';

    $clientes = null; // Inicia variável para armazenar clientes
    $mensagem = '';

    if (isset($_GET['excluir'])) {
        if ($_GET['excluir'] === 'sucesso') {
            $mensagem = 'Cliente excluído com sucesso!';
        } elseif ($_GET['excluir'] === 'erro') {
            $mensagem = 'Erro ao excluir o cliente.';
        }
    }

    if (isset($_POST['search_cliente'])) {
        $search_cliente = sanitize_text_field($_POST['search_cliente']);
        $clientes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE nome_responsavel LIKE %s", '%' . $wpdb->esc_like($search_cliente) . '%'));

        if (empty($clientes)) {
            $mensagem = 'Nenhum cliente encontrado com o nome "' . esc_html($search_cliente) . '".';
        }
    }

    ob_start();
    ?>
    <form method="post">
        <label for="search_cliente">Excluir Cliente:</label>
        <input type="text" name="search_cliente" id="search_cliente" placeholder="Digite o nome do cliente" value="<?php echo isset($_POST['search_cliente']) ? esc_attr($_POST['search_cliente']) : ''; ?>">
        <input type="submit" value="Pesquisar">
    </form>

    <?php if ($mensagem): ?>
        <p><?php echo esc_html($mensagem); ?></p>
    <?php endif; ?>

    <?php if (isset($clientes) && !empty($clientes)): ?>
        <h2>Resultados da Pesquisa:</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome do Responsável</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?php echo esc_html($cliente->nome_responsavel); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="cliente_id" value="<?php echo esc_attr($cliente->id); ?>">
                                <input type="submit" name="excluir_cliente" value="Excluir" onclick="return confirm('Tem certeza que deseja excluir este cliente?');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

add_shortcode('excluir_clientes', 'excluir_clientes');


//MENU
add_action('admin_menu', 'menu_credito_usuario_wp');
function menu_credito_usuario_wp() {
    add_menu_page(
        'Controle de Créditos',
        'Créditos de Agendamento',
        'manage_options',
        'controle_creditos',
        'pagina_creditos_usuario_wp'
    );
}

function pagina_creditos_usuario_wp() {
    global $wpdb;
    $table_limites = $wpdb->prefix . 'limite_agendamentos';

    // Salvar ou atualizar limite
    if (isset($_POST['user_id']) && isset($_POST['limite_mensal'])) {
        $user_id = intval($_POST['user_id']);
        $limite = intval($_POST['limite_mensal']);

        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_limites WHERE user_id = %d", $user_id));

        if ($existe) {
            $wpdb->update($table_limites, ['limite_mensal' => $limite], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($table_limites, ['user_id' => $user_id, 'limite_mensal' => $limite]);
        }

        echo '<div class="updated"><p>Créditos atualizados com sucesso!</p></div>';
    }

    $usuarios = get_users(['fields' => ['ID', 'display_name']]);

    ?>
    <div class="wrap">
        <h1>Adicionar Créditos para Usuários</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="user_id">Usuário:</label></th>
                    <td>
                        <select name="user_id" id="user_id" required>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= esc_attr($usuario->ID); ?>">
                                    <?= esc_html($usuario->display_name); ?> (ID: <?= $usuario->ID; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="limite_mensal">Créditos Mensais:</label></th>
                    <td><input type="number" name="limite_mensal" id="limite_mensal" required min="0"></td>
                </tr>
            </table>
            <p><input type="submit" class="button button-primary" value="Salvar Créditos"></p>
        </form>
    </div>
    <?php
}


//LIMTE DECREDITO
function usuario_atingiu_limite($user_id) {
    global $wpdb;

    if (!$user_id) return true;

    $table_agendamentos = $wpdb->prefix . 'agendamentos';
    $table_limites = $wpdb->prefix . 'limite_agendamentos';

    // Verifica limite mensal
    $limite = $wpdb->get_var($wpdb->prepare(
        "SELECT limite_mensal FROM $table_limites WHERE user_id = %d", $user_id
    ));

    if ($limite === null || $limite <= 0) return true;

    $inicio_mes = date('Y-m-01 00:00:00');
    $fim_mes = date('Y-m-t 23:59:59');

    $usados = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_agendamentos WHERE user_id = %d AND data_criacao BETWEEN %s AND %s",
        $user_id, $inicio_mes, $fim_mes
    ));

    return intval($usados) >= intval($limite);
}


//RELATORIO DECREDITO
function relatorio_creditos_usuarios() {
    global $wpdb;

    $table_agendamentos = $wpdb->prefix . 'agendamentos';
    $table_limites = $wpdb->prefix . 'limite_agendamentos';

    $usuarios = get_users();
    ob_start();

    echo "<h2>Relatório de Créditos por Usuário</h2>";
    echo "<table class='wp-list-table widefat fixed striped'>
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Créditos</th>
                    <th>Usados</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($usuarios as $usuario) {
        $limite = $wpdb->get_var($wpdb->prepare(
            "SELECT limite_mensal FROM $table_limites WHERE user_id = %d", $usuario->ID
        ));

        if ($limite === null) continue;

        $inicio_mes = date('Y-m-01 00:00:00');
        $fim_mes = date('Y-m-t 23:59:59');

        $usados = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_agendamentos WHERE user_id = %d AND data_criacao BETWEEN %s AND %s",
            $usuario->ID, $inicio_mes, $fim_mes
        ));

        $saldo = max(0, $limite - $usados);

        echo "<tr>
                <td>" . esc_html($usuario->display_name) . "</td>
                <td>$limite</td>
                <td>$usados</td>
                <td>$saldo</td>
              </tr>";
    }

    echo "</tbody></table>";

    return ob_get_clean();
}
add_shortcode('relatorio_creditos_usuarios', 'relatorio_creditos_usuarios');


