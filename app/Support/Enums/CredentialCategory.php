<?php

namespace App\Support\Enums;

enum CredentialCategory: string
{
    case Cms = 'cms';
    case LandingPage = 'landing_page';
    case Crm = 'crm';
    case Erp = 'erp';
    case Hrm = 'hrm';
    case Lms = 'lms';
    case KnowledgeBase = 'knowledge_base';
    case AiPlatform = 'ai_platform';
    case Vps = 'vps';
    case Server = 'server';
    case Hosting = 'hosting';
    case Cdn = 'cdn';
    case Dns = 'dns';
    case Domain = 'domain';
    case Database = 'database';
    case MailServer = 'mail_server';
    case Ssl = 'ssl';
    case CloudProvider = 'cloud_provider';
    case HostingProvider = 'hosting_provider';
    case SmsProvider = 'sms_provider';
    case EmailProvider = 'email_provider';
    case PaymentGateway = 'payment_gateway';
    case AiServices = 'ai_services';
    case ThirdPartyApi = 'third_party_api';
    case ApiKey = 'api_key';
    case AdminAccount = 'admin_account';
    case UserAccount = 'user_account';
    case Other = 'other';

    public function labelVi(): string
    {
        return match ($this) {
            self::Cms => 'Website CMS',
            self::LandingPage => 'Landing Page',
            self::Crm => 'CRM',
            self::Erp => 'ERP',
            self::Hrm => 'HRM',
            self::Lms => 'LMS',
            self::KnowledgeBase => 'Knowledge Base',
            self::AiPlatform => 'AI Platform',
            self::Vps => 'VPS',
            self::Server => 'Server',
            self::Hosting => 'Hosting',
            self::Cdn => 'CDN',
            self::Dns => 'DNS',
            self::Domain => 'Domain',
            self::Database => 'Database',
            self::MailServer => 'Mail Server',
            self::Ssl => 'SSL',
            self::CloudProvider => 'Cloud Provider',
            self::HostingProvider => 'Hosting Provider',
            self::SmsProvider => 'SMS Provider',
            self::EmailProvider => 'Email Provider',
            self::PaymentGateway => 'Payment Gateway',
            self::AiServices => 'AI Services',
            self::ThirdPartyApi => 'Third-party API',
            self::ApiKey => 'API Key',
            self::AdminAccount => 'Admin',
            self::UserAccount => 'User',
            self::Other => 'Khác',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
        ], self::cases());
    }
}
