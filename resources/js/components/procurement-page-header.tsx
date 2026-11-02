import ProjectPageHeader from '@/components/project-page-header';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export default function ProcurementPageHeader({
    title,
    subtitle,
    icon: Icon,
    meta,
    actions,
    backHref,
    embedded = false,
    showEyebrow = true,
    compact = false,
}: {
    title: string;
    subtitle?: string;
    icon: LucideIcon;
    meta?: ReactNode;
    actions?: ReactNode;
    backHref?: string;
    embedded?: boolean;
    showEyebrow?: boolean;
    compact?: boolean;
}) {
    return (
        <ProjectPageHeader
            eyebrow={showEyebrow ? 'Procurement' : undefined}
            title={title}
            subtitle={subtitle}
            icon={<Icon className="size-5" />}
            meta={meta}
            actions={actions}
            backHref={backHref}
            embedded={embedded}
            compact={compact}
        />
    );
}
