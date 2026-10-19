<?php

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: ../pages/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];

if (is_array($usuario)) {
    $nomeUsuario =
        $usuario['nomeCompleto']
        ?? $usuario['nome']
        ?? $usuario['nome_completo']
        ?? 'Usuário';
} else {
    $nomeUsuario = 'Usuário';
}

$nomeUsuario = htmlspecialchars(
    $nomeUsuario,
    ENT_QUOTES,
    'UTF-8'
);


/*
|--------------------------------------------------------------------------
| UNIDADES DE SAÚDE DE PATOS - PB
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| As imagens abaixo são somente de fachadas/prédios das unidades.
| Quando não foi encontrada uma foto confiável da própria unidade,
| o campo imagem fica vazio.
|
|--------------------------------------------------------------------------
*/

$unidades = [

    /*
    |--------------------------------------------------------------------------
    | HOSPITAL
    |--------------------------------------------------------------------------
    */

    [
        'nome' => 'Hospital Regional de Patos Deputado Janduhy Carneiro',
        'tipo' => 'Hospital',
        'bairro' => 'Belo Horizonte',
        'endereco' => 'Patos - PB',
        'horario' => 'Atendimento hospitalar',
        'atendimento' => 'Urgência e emergência',
        'telefone' => '(83) 3415-7700',
        'social' => '',
        'imagem' => 'https://pbnews.com.br/images/noticias/28470/a1c36132cfb43ef9854ea260c4416445.jpg'
    ],

    /*
    |--------------------------------------------------------------------------
    | MATERNIDADE
    |--------------------------------------------------------------------------
    */

    [
        'nome' => 'Maternidade Dr. Peregrino Filho',
        'tipo' => 'Maternidade',
        'bairro' => 'Maternidade',
        'endereco' => 'Patos - PB',
        'horario' => 'Atendimento hospitalar',
        'atendimento' => 'Urgência, emergência e atendimento obstétrico',
        'telefone' => '(83) 3415-7600 / (83) 3423-2320',
        'social' => '',
        'imagem' => 'https://www.submit.10envolve.com.br/uploads/060c211a683eb92a9288c98a2a5c77fdb7d2d2c7/29cf881fb407297482a86301a32652de.jpg'
    ],

    /*
    |--------------------------------------------------------------------------
    | UPAs
    |--------------------------------------------------------------------------
    */

    [
        'nome' => 'UPA João Bosco de Araújo',
        'tipo' => 'UPA',
        'bairro' => 'Patos',
        'endereco' => 'Patos - PB',
        'horario' => '24 horas',
        'atendimento' => 'Urgência e emergência',
        'telefone' => 'Consultar unidade',
        'social' => '@upa_joaoboscoaraujo',
        'imagem' => ''
    ],

    [
        'nome' => 'UPA Dr. Otávio Pires',
        'tipo' => 'UPA',
        'bairro' => 'Patos',
        'endereco' => 'Patos - PB',
        'horario' => '24 horas',
        'atendimento' => 'Urgência e emergência',
        'telefone' => '(83) 99921-9629',
        'social' => '@upadrotaviopires',
        'imagem' => 'https://www.polemicaparaiba.com.br/wp-content/uploads/2017/07/upa-Patos.jpg'
    ],


    /*
    |--------------------------------------------------------------------------
    | UBS
    |--------------------------------------------------------------------------
    */

    [
        'nome' => 'UBS Rita Palmeira',
        'tipo' => 'UBS',
        'bairro' => 'Bela Vista',
        'endereco' => 'Rua Juvenal Lúcio, 199, Bela Vista, Patos - PB, 58704-500',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '@ubsritapalmeira',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Aderban Martins de Medeiros',
        'tipo' => 'UBS',
        'bairro' => 'Belo Horizonte',
        'endereco' => 'Rua Enaldo Torres Fernandes, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS José Maurício Cajuaz',
        'tipo' => 'UBS',
        'bairro' => 'Belo Horizonte',
        'endereco' => 'Rua Pedro II, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Verônica Maria Vieira',
        'tipo' => 'UBS',
        'bairro' => 'Belo Horizonte',
        'endereco' => 'Rua Luís José, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosonline.com/images/noticias/252006/fee3b0aa2d9f46d7457cd256f22a258b.jpeg'
    ],

    [
        'nome' => 'UBS José de Oliveira Pio',
        'tipo' => 'UBS',
        'bairro' => 'Bivar Olinto',
        'endereco' => 'Rua José Mesquita, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Sebastiana Xavier',
        'tipo' => 'UBS',
        'bairro' => 'Bivar Olinto',
        'endereco' => 'Rua Zózimo Gurgel, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Antônio Urquiza',
        'tipo' => 'UBS',
        'bairro' => 'Santa Gertrudes',
        'endereco' => 'Distrito de Santa Gertrudes, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patos.pb.gov.br/images/fotos_p2_news/69291c64228ae_WhatsApp_Image_2025-11-27_at_21.43.11%281%29.jpeg'
    ],

    [
        'nome' => 'UBS Dirce Xavier',
        'tipo' => 'UBS',
        'bairro' => 'Centro',
        'endereco' => 'Rua Duque de Caxias, 143, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS São Judas Tadeu',
        'tipo' => 'UBS',
        'bairro' => 'Conjunto Habitacional',
        'endereco' => 'Rua Severino Lustosa, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Pedro Firmino Filho',
        'tipo' => 'UBS',
        'bairro' => 'Frei Damião',
        'endereco' => 'Rua Natália de Figueiredo, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosonline.com/images/noticias/214780/214780_UBS-Pedro-Firmino.jpeg'
    ],

    [
        'nome' => 'UBS Geraldo Gomes de Carvalho',
        'tipo' => 'UBS',
        'bairro' => 'Jatobá',
        'endereco' => 'Rua Dino Guedes, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Lauro Queiroz',
        'tipo' => 'UBS',
        'bairro' => 'Jatobá',
        'endereco' => 'Rua Manoel Reinaldo, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Maria Marques',
        'tipo' => 'UBS',
        'bairro' => 'Jatobá',
        'endereco' => 'Rua Manoel Mota, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosverdade.com.br/envios/2022/07/19/978cf993a25d5fa587f7a2757924f2fbef8becd2.jpg'
    ],

    [
        'nome' => 'UBS Ernesto Soares Alves',
        'tipo' => 'UBS',
        'bairro' => 'Jardim Bela Vista',
        'endereco' => 'Rua Venâncio Costa, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Leomar Silva',
        'tipo' => 'UBS',
        'bairro' => 'Liberdade',
        'endereco' => 'Rua Zeca Vieira, 249, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Rosinha Xavier',
        'tipo' => 'UBS',
        'bairro' => 'Liberdade',
        'endereco' => 'Rua do Prado, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Maria Madalena do Espírito Santo',
        'tipo' => 'UBS',
        'bairro' => 'Matadouro',
        'endereco' => 'Rua Projetada, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Ana Raquel',
        'tipo' => 'UBS',
        'bairro' => 'Maternidade',
        'endereco' => 'Rua Luiz Araújo, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Domiciano Vieira',
        'tipo' => 'UBS',
        'bairro' => 'Maternidade',
        'endereco' => 'Basta Gomes, 713, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Doraci Brito',
        'tipo' => 'UBS',
        'bairro' => 'Maternidade',
        'endereco' => 'Rua Pedro Cruz Guedes, 1812, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Aderbal Martins',
        'tipo' => 'UBS',
        'bairro' => 'Monte Castelo',
        'endereco' => 'Rua Sebastião Monteiro, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Metódio de Araújo Leitão',
        'tipo' => 'UBS',
        'bairro' => 'Monte Castelo',
        'endereco' => 'Rua Sabino Viana, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Pedro Leandro Sobrinho',
        'tipo' => 'UBS',
        'bairro' => 'Monte Castelo',
        'endereco' => 'Rua Antônio Torres, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Alexandra Kollontai',
        'tipo' => 'UBS',
        'bairro' => 'Morada do Sol',
        'endereco' => 'Rua Vereador Severino Alves Siqueira, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Bivar Olintho',
        'tipo' => 'UBS',
        'bairro' => 'Morro',
        'endereco' => 'Rua Severino Dutra, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Evaristo Medeiros Guedes',
        'tipo' => 'UBS',
        'bairro' => 'Mutirão',
        'endereco' => 'Rua Celina Gondim dos Anjos, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosonline.com/images/noticias/271297/aaae0225ead561306462da12c26efcda.webp'
    ],

    [
        'nome' => 'UBS Walter Ayres',
        'tipo' => 'UBS',
        'bairro' => 'Noé Trajano',
        'endereco' => 'Rua Severino Inácio, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Manoel Pereira de Sousa',
        'tipo' => 'UBS',
        'bairro' => 'Novo Horizonte',
        'endereco' => 'Rua Manoel Medeiros de Oliveira, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Solon Medeiros',
        'tipo' => 'UBS',
        'bairro' => 'Salgadinho',
        'endereco' => 'Rua Manoel Torres, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Belmiro Guedes',
        'tipo' => 'UBS',
        'bairro' => 'Santo Antônio',
        'endereco' => 'Rua Alexandrino Rodrigues, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosonline.com/images/noticias/255984/b8d89ca9926dbc1934fc221688f7761c.webp'
    ],

    [
        'nome' => 'UBS Carleusa Candeia',
        'tipo' => 'UBS',
        'bairro' => 'Santo Antônio',
        'endereco' => 'Rua Escritor Augusto dos Anjos, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Osman Ayres',
        'tipo' => 'UBS',
        'bairro' => 'Santo Antônio',
        'endereco' => 'Rua Elias Asfora, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Enaldo Torres Fernandes',
        'tipo' => 'UBS',
        'bairro' => 'São Sebastião',
        'endereco' => 'Rua Alfredo Lustosa Cabral, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Horácio Nóbrega',
        'tipo' => 'UBS',
        'bairro' => 'São Sebastião',
        'endereco' => 'Rua Lima Campos, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, manhã, tarde e noite',
        'atendimento' => 'Atenção básica',
        'telefone' => '(83) 3421-6186',
        'social' => '',
        'imagem' => 'https://www.submit.10envolve.com.br/uploads/060c211a683eb92a9288c98a2a5c77fdb7d2d2c7/b2a701a91d006507d804e133f08dab9d.jpeg'
    ],

    [
        'nome' => 'UBS Roberto Oba',
        'tipo' => 'UBS',
        'bairro' => 'São Sebastião',
        'endereco' => 'Rua Aurino Pereira, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => 'https://patosonline.com/images/noticias/253683/e9653a0c158ea04a08af989e635f1c25.webp'
    ],

    [
        'nome' => 'UBS João Soares',
        'tipo' => 'UBS',
        'bairro' => 'Sete Casas',
        'endereco' => 'Rua Pedro Moura, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Nabor Wanderley',
        'tipo' => 'UBS',
        'bairro' => 'Vila Cavalcante',
        'endereco' => 'Rua Pedro Moura, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Diego Lucena Camboim',
        'tipo' => 'UBS',
        'bairro' => 'Vila Mariana',
        'endereco' => 'Rua Severina Dantas, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Ministro Ernani Satyro',
        'tipo' => 'UBS',
        'bairro' => 'Vitória',
        'endereco' => 'Travessa Euclides Franco, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ],

    [
        'nome' => 'UBS Yoyo Laureano',
        'tipo' => 'UBS',
        'bairro' => 'Alto da Tubiba',
        'endereco' => 'Rua Antônio Tranca Rua, s/n, Patos - PB',
        'horario' => 'Segunda a sexta, horário comercial',
        'atendimento' => 'Atenção básica',
        'telefone' => 'Consultar unidade',
        'social' => '',
        'imagem' => ''
    ]
];


/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$totalUBS = 0;
$totalHospitalar = 0;

foreach ($unidades as $unidade) {

    if ($unidade['tipo'] === 'UBS') {
        $totalUBS++;
    } else {
        $totalHospitalar++;
    }
}

$totalUnidades = count($unidades);

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Mapa da Saúde - Saúde-Conecta
    </title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7f9;

            color: #263238;

        }


        header {

            background:
                linear-gradient(
                    135deg,
                    #00695c,
                    #00897b
                );

            color: white;

            padding: 22px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,.15);

        }


        .header-conteudo {

            max-width: 1400px;

            margin: auto;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            flex-wrap: wrap;

        }


        .titulo h1 {

            font-size: 28px;

            margin-bottom: 5px;

        }


        .titulo p {

            opacity: .9;

        }


        .acoes {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        .botao {

            display: inline-block;

            padding: 11px 17px;

            border-radius: 9px;

            text-decoration: none;

            font-weight: bold;

            color: white;

            background:
                rgba(255,255,255,.16);

            border:
                1px solid
                rgba(255,255,255,.35);

            transition: .2s;

        }


        .botao:hover {

            background:
                rgba(255,255,255,.28);

        }


        .principal {

            max-width: 1400px;

            margin: 25px auto;

            padding: 0 18px;

        }


        .boas-vindas {

            background: white;

            border-radius: 16px;

            padding: 24px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,.07);

        }


        .boas-vindas h2 {

            color: #00695c;

            margin-bottom: 8px;

        }


        .estatisticas {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 20px;

        }


        .estatistica {

            background: white;

            border-radius: 14px;

            padding: 20px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,.07);

            border-left:
                5px solid #00897b;

        }


        .estatistica strong {

            display: block;

            font-size: 30px;

            color: #00695c;

            margin-bottom: 5px;

        }


        .conteudo {

            display: grid;

            grid-template-columns:
                minmax(0, 1.15fr)
                minmax(350px, .85fr);

            gap: 20px;

            align-items: start;

        }


        .lista {

            display: grid;

            gap: 18px;

        }


        .card {

            background: white;

            border-radius: 16px;

            overflow: hidden;

            box-shadow:
                0 5px 18px
                rgba(0,0,0,.08);

            border:
                1px solid #e5eaea;

        }


        .imagem {

            width: 100%;

            height: 250px;

            background:
                linear-gradient(
                    135deg,
                    #e0f2f1,
                    #b2dfdb
                );

            overflow: hidden;

        }


        .imagem img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;

        }


        .sem-imagem {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            text-align: center;

            padding: 25px;

            color: #00695c;

            font-weight: bold;

            font-size: 16px;

        }


        .card-conteudo {

            padding: 20px;

        }


        .tag {

            display: inline-block;

            background: #e0f2f1;

            color: #00695c;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .card h3 {

            color: #263238;

            font-size: 20px;

            margin-bottom: 12px;

        }


        .informacao {

            display: flex;

            gap: 10px;

            margin: 9px 0;

            line-height: 1.45;

        }


        .informacao strong {

            min-width: 105px;

            color: #00695c;

        }


        .telefone {

            color: #00695c;

            font-weight: bold;

            text-decoration: none;

        }


        .telefone:hover {

            text-decoration: underline;

        }


        .social {

            color: #00695c;

            font-weight: bold;

        }


        .botoes-card {

            display: flex;

            gap: 10px;

            margin-top: 17px;

            flex-wrap: wrap;

        }


        .botao-mapa {

            border: none;

            cursor: pointer;

            padding: 11px 15px;

            border-radius: 9px;

            background: #00897b;

            color: white;

            font-weight: bold;

        }


        .botao-mapa:hover {

            background: #00695c;

        }


        .botao-rota {

            padding: 11px 15px;

            border-radius: 9px;

            background: #eceff1;

            color: #263238;

            font-weight: bold;

            text-decoration: none;

        }


        .botao-rota:hover {

            background: #cfd8dc;

        }


        .mapa {

            position: sticky;

            top: 20px;

            background: white;

            border-radius: 16px;

            overflow: hidden;

            box-shadow:
                0 5px 18px
                rgba(0,0,0,.08);

        }


        .mapa-titulo {

            padding: 18px;

            background: #00695c;

            color: white;

        }


        .mapa-titulo h2 {

            font-size: 20px;

            margin-bottom: 5px;

        }


        #mapaGoogle {

            width: 100%;

            height: 650px;

            border: 0;

            display: block;

        }


        @media (max-width: 1000px) {

            .conteudo {

                grid-template-columns: 1fr;

            }

            .mapa {

                position: relative;

                top: 0;

            }

        }


        @media (max-width: 700px) {

            .estatisticas {

                grid-template-columns: 1fr;

            }

            .titulo h1 {

                font-size: 23px;

            }

            .informacao {

                display: block;

            }

            .informacao strong {

                display: block;

                margin-bottom: 3px;

            }

            #mapaGoogle {

                height: 450px;

            }

        }

    </style>

</head>


<body>


<header>

    <div class="header-conteudo">

        <div class="titulo">

            <h1>
                Mapa da Saúde
            </h1>

            <p>
                Olá, <?= $nomeUsuario ?>.
                Encontre unidades de saúde em Patos - PB.
            </p>

        </div>


        <div class="acoes">

            <a
                href="perfil_saude.php"
                class="botao"
            >
                Perfil de Saúde
            </a>


            <a
                href="logout.php"
                class="botao"
            >
                Sair
            </a>

        </div>

    </div>

</header>


<main class="principal">


    <section class="boas-vindas">

        <h2>
            Unidades de Saúde de Patos
        </h2>

        <p>
            Consulte endereço, horário,
            atendimento, telefone e localização
            das unidades cadastradas.
        </p>

    </section>


    <section class="estatisticas">


        <div class="estatistica">

            <strong>
                <?= $totalUBS ?>
            </strong>

            UBS cadastradas

        </div>


        <div class="estatistica">

            <strong>
                <?= $totalHospitalar ?>
            </strong>

            Hospitais, maternidade e UPAs

        </div>


        <div class="estatistica">

            <strong>
                <?= $totalUnidades ?>
            </strong>

            Unidades no mapa

        </div>


    </section>


    <section class="conteudo">


        <div class="lista">


            <?php foreach ($unidades as $unidade): ?>

                <?php

                $nomeMapa = urlencode(
                    $unidade['nome'] .
                    ', ' .
                    $unidade['endereco']
                );

                $rota =
                    'https://www.google.com/maps/search/?api=1&query='
                    . $nomeMapa;

                ?>


                <article class="card">


                    <div class="imagem">


                        <?php if (!empty($unidade['imagem'])): ?>


                            <img
                                src="<?= htmlspecialchars(
                                    $unidade['imagem'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="Fachada da <?= htmlspecialchars(
                                    $unidade['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                loading="lazy"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="sem-imagem"
                                style="display:none;"
                            >
                                Foto da fachada indisponível.
                            </div>


                        <?php else: ?>


                            <div class="sem-imagem">

                                Foto da fachada da unidade
                                ainda não encontrada
                                em fonte pública confiável.

                            </div>


                        <?php endif; ?>


                    </div>


                    <div class="card-conteudo">


                        <span class="tag">

                            <?= htmlspecialchars(
                                $unidade['tipo'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <h3>

                            <?= htmlspecialchars(
                                $unidade['nome'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>


                        <div class="informacao">

                            <strong>
                                Bairro:
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $unidade['bairro'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <div class="informacao">

                            <strong>
                                Endereço:
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $unidade['endereco'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <div class="informacao">

                            <strong>
                                Horário:
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $unidade['horario'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <div class="informacao">

                            <strong>
                                Atendimento:
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $unidade['atendimento'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <div class="informacao">

                            <strong>
                                Telefone:
                            </strong>

                            <span>


                                <?php if (
                                    !empty($unidade['telefone']) &&
                                    $unidade['telefone'] !== 'Consultar unidade'
                                ): ?>


                                    <?php

                                    $telefoneLink =
                                        preg_replace(
                                            '/[^0-9+]/',
                                            '',
                                            $unidade['telefone']
                                        );

                                    ?>


                                    <a
                                        href="tel:<?= $telefoneLink ?>"
                                        class="telefone"
                                    >
                                        <?= htmlspecialchars(
                                            $unidade['telefone'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </a>


                                <?php else: ?>


                                    Consultar unidade


                                <?php endif; ?>


                            </span>

                        </div>


                        <?php if (!empty($unidade['social'])): ?>


                            <div class="informacao">

                                <strong>
                                    Instagram:
                                </strong>

                                <span class="social">

                                    <?= htmlspecialchars(
                                        $unidade['social'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </div>


                        <?php endif; ?>


                        <div class="botoes-card">


                            <button
                                type="button"
                                class="botao-mapa"
                                onclick="abrirNoMapa(
                                    <?= htmlspecialchars(
                                        json_encode(
                                            $unidade['nome'] .
                                            ', ' .
                                            $unidade['endereco'],
                                            JSON_UNESCAPED_UNICODE
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                )"
                            >
                                Ver no mapa
                            </button>


                            <a
                                href="<?= htmlspecialchars(
                                    $rota,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="botao-rota"
                            >
                                Como chegar
                            </a>


                        </div>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


        <aside
            class="mapa"
            id="mapaArea"
        >


            <div class="mapa-titulo">

                <h2>
                    Localização
                </h2>

                <p>
                    Clique em "Ver no mapa" para
                    localizar uma unidade.
                </p>

            </div>


            <iframe
                id="mapaGoogle"
                src="https://www.google.com/maps?q=Patos%2C%20Para%C3%ADba%2C%20Brasil&output=embed"
                loading="lazy"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>


        </aside>


    </section>


</main>


<script>

function abrirNoMapa(endereco) {

    const mapa =
        document.getElementById('mapaGoogle');

    const area =
        document.getElementById('mapaArea');

    const enderecoCodificado =
        encodeURIComponent(endereco);

    mapa.src =
        'https://www.google.com/maps?q=' +
        enderecoCodificado +
        '&output=embed';

    area.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });

}

</script>


</body>

</html>