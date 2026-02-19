<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f7f7f7;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 20px;
            border: 4px solid #1f2937;
        }

        .header {
            text-align: center;
            border-bottom: 4px solid #1f2937;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            color: #1f2937;
            letter-spacing: -1px;
        }

        .content {
            color: #4b5563;
            font-size: 16px;
            line-height: 1.6;
        }

        .highlight {
            font-weight: bold;
            color: #1f2937;
        }

        .date-box {
            background-color: #f0fdf4;
            border: 2px solid #16a34a;
            color: #166534;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 20px;
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">Tejenderas</div>
            <div style="font-size: 12px; letter-spacing: 2px; color: #6b7280; font-weight: bold;">UNIFORMES & TEXTILES
            </div>
        </div>

        <div class="content">
            <p>Estimado/a <span class="highlight">{{ $contract->contact_person }}</span>,</p>

            <p>Le escribimos en relación a su pedido para <strong>{{ $contract->company_name }}</strong> (Orden
                #{{ str_pad($contract->id, 6, '0', STR_PAD_LEFT) }}).</p>

            <p>Queremos informarle que hemos actualizado la fecha estimada de entrega de su pedido.
                {{ $reason ? 'El motivo de este cambio es: ' . $reason : '' }}</p>

            <p>Su nueva fecha de entrega programada es:</p>

            <div class="date-box">
                {{ \Carbon\Carbon::parse($contract->delivery_date)->format('d/m/Y') }}
            </div>

            <p>Agradecemos su comprensión y paciencia. Estamos comprometidos con entregarle productos de la más alta
                calidad.</p>
        </div>

        <div class="footer">
            <p>Este es un mensaje automático, por favor no responder directamente a este correo.</p>
            <p>&copy; {{ date('Y') }} Tejenderas. Todos los derechos reservados.</p>
        </div>
    </div>
</body>

</html>