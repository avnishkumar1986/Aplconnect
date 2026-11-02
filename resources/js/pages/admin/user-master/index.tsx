import InputError from '@/components/input-error';
import { DataTableSortHeader } from '@/components/master/data-table-sort-header';
import { MasterDialogHeader } from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Edit3,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    Plus,
    Search,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

type UserType = { uid: string; name: string };
type Department = { id: number; department_name: string };
type CompanyOption = { company_code: string; company_name: string };
type SittingLocationOption = CompanyOption & { parent_company_id: string | null; record_type: number };
type Designation = { id: number; designation_name: string; department_id: number | null };
type Role = { id: number; name: string };
type Address = {
    address_type: string;
    is_primary: boolean;
    address_line_1: string;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    district: string | null;
    postal_code: string | null;
    country: string | null;
    latitude: number | null;
    longitude: number | null;
};
type Contact = {
    id: number;
    contact_type: string;
    email: string | null;
    contact_number: string | null;
    is_primary: boolean;
    is_verified: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
};
type ContactForm = { id?: number; contact_type: string; contact_value: string; is_primary: boolean };
type EducationForm = {
    id?: number;
    education_level: string;
    degree_name: string;
    institution_name: string;
    university_name: string;
    start_date: string;
    end_date: string;
    grade: string;
    currently_studying: boolean;
    document: File | null;
    document_path?: string | null;
};
type Banking = {
    bank_name: string;
    account_number: string;
    account_type: string | null;
    branch_name: string | null;
    ifsc_code: string | null;
};
type MasterUser = {
    id: number;
    user_type: string;
    unique_id: string | null;
    name: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    gender: string | null;
    marital_status: string | null;
    date_of_birth: string | null;
    anniversary_date: string | null;
    aadhaar_number: string | null;
    aadhaar_image: string | null;
    pan_number: string | null;
    pan_image: string | null;
    company: string | null;
    sitting_location: string | null;
    department_id: number | null;
    designation_id: number | null;
    email: string;
    status: boolean;
    created_at: string;
    user_type: UserType | null;
    primary_email: { email: string; is_verified: boolean; email_verified_at: string | null } | null;
    login: { username: string; last_login_at: string | null } | null;
    addresses: Address[];
    contacts: Contact[];
    educations: Array<Omit<EducationForm, 'document'>>;
    banking: Banking | null;
    roles: Role[];
    department_assignment: {
        role_id: number | null;
        role: Role | null;
    } | null;
    primary_photo: { file_path: string; alt_text: string | null } | null;
};

type UserForm = {
    first_name: string;
    middle_name: string;
    last_name: string;
    unique_id: string;
    gender: string;
    marital_status: string;
    date_of_birth: string;
    anniversary_date: string;
    aadhaar_number: string;
    aadhaar_image: File | null;
    pan_number: string;
    pan_image: File | null;
    user_type: string;
    company: string;
    sitting_location: string;
    department_id: string;
    designation_id: string;
    role: string;
    email: string;
    username: string;
    password: string;
    contacts: ContactForm[];
    educations: EducationForm[];
    bank_name: string;
    account_number: string;
    account_type: string;
    branch_name: string;
    ifsc_code: string;
    address_type: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    district: string;
    postal_code: string;
    country: string;
    latitude: string;
    longitude: string;
    permanent_same_as_local: boolean;
    permanent_address_line_1: string;
    permanent_address_line_2: string;
    permanent_city: string;
    permanent_state: string;
    permanent_district: string;
    permanent_postal_code: string;
    permanent_country: string;
    permanent_latitude: string;
    permanent_longitude: string;
};
type PaginatedUsers = {
    data: MasterUser[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = { search: string; per_page: number; sort: string; direction: 'asc' | 'desc' };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Employee', href: '/admin/user-master' }];
const formSteps = ['Personal & employment', 'Contact', 'Address & map', 'Education', 'Banking', 'Login'] as const;
const educationLevels = [
    'Secondary (10th)',
    'Higher Secondary (12th)',
    'ITI',
    'Diploma',
    'Undergraduate',
    'Graduate',
    'Postgraduate',
    'Doctorate (PhD)',
    'Post Doctorate',
] as const;
const emptyForm: UserForm = {
    first_name: '',
    middle_name: '',
    last_name: '',
    unique_id: '',
    gender: '',
    marital_status: '',
    date_of_birth: '',
    anniversary_date: '',
    aadhaar_number: '',
    aadhaar_image: null,
    pan_number: '',
    pan_image: null,
    user_type: 'USR6',
    company: 'APL Apollo',
    sitting_location: '',
    department_id: '',
    designation_id: '',
    role: 'Viewer',
    email: '',
    username: '',
    password: '',
    contacts: [{ contact_type: 'mobile', contact_value: '', is_primary: true }],
    educations: [
        {
            education_level: '',
            degree_name: '',
            institution_name: '',
            university_name: '',
            start_date: '',
            end_date: '',
            grade: '',
            currently_studying: false,
            document: null,
        },
    ],
    bank_name: '',
    account_number: '',
    account_type: '',
    branch_name: '',
    ifsc_code: '',
    address_type: 'local',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    district: '',
    postal_code: '',
    country: 'India',
    latitude: '',
    longitude: '',
    permanent_same_as_local: false,
    permanent_address_line_1: '',
    permanent_address_line_2: '',
    permanent_city: '',
    permanent_state: '',
    permanent_district: '',
    permanent_postal_code: '',
    permanent_country: 'India',
    permanent_latitude: '',
    permanent_longitude: '',
};

function addressMapUrl(latitude: string, longitude: string) {
    if (!latitude.trim() || !longitude.trim()) return '';
    const lat = Number(latitude);
    const lon = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) return '';
    const delta = 0.012;
    return `https://www.openstreetmap.org/export/embed.html?bbox=${lon - delta}%2C${lat - delta}%2C${lon + delta}%2C${lat + delta}&layer=mapnik&marker=${lat}%2C${lon}`;
}

function formatDate(value: string | null) {
    if (!value) return 'Never';
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

function dateForInput(value: string | null) {
    if (!value) return '';
    const [year, month, day] = value.slice(0, 10).split('-');

    return year && month && day ? `${day}-${month}-${year}` : value;
}

function dateForDatabase(value: string) {
    const match = value.trim().match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
    if (!match) return value;

    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

const hasText = (value: unknown) => String(value ?? '').trim();

export default function UserMaster({
    users,
    departments,
    companies,
    sittingLocations,
    designations,
    roles,
    filters,
    pageSizeOptions,
    stats,
    canManage,
}: {
    users: PaginatedUsers;
    userTypes: UserType[];
    departments: Department[];
    companies: CompanyOption[];
    sittingLocations: SittingLocationOption[];
    designations: Designation[];
    roles: Role[];
    filters: Filters;
    pageSizeOptions: number[];
    stats: { total: number; active: number };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search);
    const [open, setOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<MasterUser | null>(null);
    const explicitSubmitRef = useRef(false);
    const [formStep, setFormStep] = useState(0);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkAction, setBulkAction] = useState('');
    const [importOpen, setImportOpen] = useState(false);
    const [readingDocument, setReadingDocument] = useState<'aadhaar' | 'pan' | null>(null);
    const [geocodingAddress, setGeocodingAddress] = useState<'local' | 'permanent' | null>(null);
    const [mapError, setMapError] = useState('');
    const [activeAddress, setActiveAddress] = useState<'local' | 'permanent'>('local');
    const [otpOpen, setOtpOpen] = useState(false);
    const [otpChannel, setOtpChannel] = useState<'email' | 'phone'>('email');
    const [otpContactId, setOtpContactId] = useState<number | null>(null);
    const [otpCode, setOtpCode] = useState('');
    const [otpError, setOtpError] = useState('');
    const [otpSeconds, setOtpSeconds] = useState(0);
    const [otpProcessing, setOtpProcessing] = useState(false);
    const form = useForm<UserForm>(emptyForm);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const geocodeAddress = async (type: 'local' | 'permanent') => {
        const prefix = type === 'local' ? '' : 'permanent_';
        const params = new URLSearchParams({
            postal_code: String(form.data[`${prefix}postal_code` as keyof UserForm] ?? ''),
            city: String(form.data[`${prefix}city` as keyof UserForm] ?? ''),
            state: String(form.data[`${prefix}state` as keyof UserForm] ?? ''),
            district: String(form.data[`${prefix}district` as keyof UserForm] ?? ''),
        });
        if (![...params.values()].some(Boolean)) {
            setMapError('Select a pincode, city or district before fetching the map.');
            return;
        }
        setMapError('');
        setGeocodingAddress(type);
        try {
            const response = await fetch(`/admin/user-master/location-coordinates?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const result = (await response.json()) as { latitude?: string; longitude?: string; message?: string };
            if (!response.ok || !result.latitude || !result.longitude) {
                setMapError(result.message ?? 'No saved map coordinates were found for this location.');
                return;
            }
            if (type === 'local') {
                form.setData({ ...form.data, latitude: result.latitude, longitude: result.longitude });
            } else {
                form.setData({ ...form.data, permanent_latitude: result.latitude, permanent_longitude: result.longitude });
            }
        } catch {
            setMapError('Unable to read map coordinates from the database.');
        } finally {
            setGeocodingAddress(null);
        }
    };
    useEffect(() => {
        if (search === filters.search) return;

        const timeout = window.setTimeout(() => {
            router.get(
                '/admin/user-master',
                { search, per_page: filters.per_page, sort: filters.sort, direction: filters.direction },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search, filters.per_page, filters.sort, filters.direction]);
    useEffect(() => {
        if (otpSeconds <= 0) return;
        const timer = window.setInterval(() => setOtpSeconds((seconds) => Math.max(0, seconds - 1)), 1000);
        return () => window.clearInterval(timer);
    }, [otpSeconds]);
    const readIdentityDocument = async (type: 'aadhaar' | 'pan', file: File | null) => {
        form.setData(type === 'aadhaar' ? 'aadhaar_image' : 'pan_image', file);
        if (!file) return;
        setReadingDocument(type);
        try {
            const { createWorker } = await import('tesseract.js');
            const worker = await createWorker('eng');
            const result = await worker.recognize(file);
            await worker.terminate();
            const text = result.data.text.toUpperCase();
            if (type === 'aadhaar') {
                const match = text.match(/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/);
                if (match) form.setData('aadhaar_number', match[0].replace(/\D/g, ''));
                else form.setError('aadhaar_number', 'Aadhaar number could not be read. Enter it manually.');
            } else {
                const match = text.match(/\b[A-Z]{5}[0-9]{4}[A-Z]\b/);
                if (match) form.setData('pan_number', match[0]);
                else form.setError('pan_number', 'PAN number could not be read. Enter it manually.');
            }
        } catch {
            form.setError(type === 'aadhaar' ? 'aadhaar_number' : 'pan_number', 'Image could not be read. Enter the number manually.');
        } finally {
            setReadingDocument(null);
        }
    };
    const loadTable = (nextSearch: string, perPage = filters.per_page, sort = filters.sort, direction = filters.direction) =>
        router.get(
            '/admin/user-master',
            { search: nextSearch, per_page: perPage, sort, direction },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const sortTable = (column: string) =>
        loadTable(search, filters.per_page, column, filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc');
    const allSelected = users.data.length > 0 && users.data.every((user) => selectedIds.includes(user.id));
    const toggleAll = (checked: boolean) => setSelectedIds(checked ? users.data.map((user) => user.id) : []);
    const runBulkAction = () => {
        if (!bulkAction || selectedIds.length === 0) return;
        if (bulkAction === 'delete' && !window.confirm(`Delete ${selectedIds.length} selected user(s)?`)) return;
        router.patch(
            '/admin/user-master/bulk',
            { ids: selectedIds, action: bulkAction },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setBulkAction('');
                },
            },
        );
    };

    const openCreate = () => {
        setEditingUser(null);
        setFormStep(0);
        setActiveAddress('local');
        form.setData({
            ...emptyForm,
            user_type: 'USR6',
            company: companies.find((company) => company.company_code === '1000')?.company_name ?? companies[0]?.company_name ?? '',
            role: roles.find((role) => role.name === 'Viewer')?.name ?? roles[0]?.name ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };
    const openEdit = (user: MasterUser) => {
        const localAddress =
            user.addresses.find((address) => address.address_type === 'local') ?? user.addresses.find((address) => address.is_primary);
        const permanentAddress = user.addresses.find((address) => address.address_type === 'permanent');
        const hasEducation = user.educations.some((education) =>
            Boolean(education.education_level || education.institution_name || education.university_name || education.grade),
        );
        const hasBanking = Boolean(user.banking?.bank_name && user.banking?.account_number);
        setEditingUser(user);
        setFormStep(!hasEducation ? 3 : !hasBanking ? 4 : 0);
        setActiveAddress('local');
        form.setData({
            first_name: user.first_name ?? '',
            middle_name: user.middle_name ?? '',
            last_name: user.last_name ?? '',
            unique_id: user.unique_id ?? '',
            gender: user.gender?.toLowerCase() ?? '',
            marital_status: user.marital_status?.toLowerCase() ?? '',
            date_of_birth: dateForInput(user.date_of_birth),
            anniversary_date: dateForInput(user.anniversary_date),
            aadhaar_number: user.aadhaar_number ?? '',
            aadhaar_image: null,
            pan_number: user.pan_number ?? '',
            pan_image: null,
            user_type: 'USR6',
            company: user.company ?? '',
            sitting_location: user.sitting_location ?? '',
            department_id: user.department_id ? String(user.department_id) : '',
            designation_id: user.designation_id ? String(user.designation_id) : '',
            role:
                user.department_assignment?.role?.name ??
                user.roles[0]?.name ??
                roles.find((role) => role.name === 'Viewer')?.name ??
                roles[0]?.name ??
                '',
            email: user.email ?? '',
            username: user.login?.username ?? user.email ?? '',
            password: '',
            contacts:
                user.contacts.length > 0
                    ? user.contacts.map((contact) => ({
                          id: contact.id,
                          contact_type: contact.contact_type === 'primary' ? 'mobile' : contact.contact_type,
                          contact_value: contact.contact_number ?? '',
                          is_primary: contact.is_primary,
                      }))
                    : [{ contact_type: 'mobile', contact_value: '', is_primary: true }],
            educations:
                user.educations.length > 0
                    ? user.educations.map((education) => ({
                          ...education,
                          start_date: education.start_date ?? '',
                          end_date: education.end_date ?? '',
                          document: null,
                      }))
                    : [
                          {
                              education_level: '',
                              degree_name: '',
                              institution_name: '',
                              university_name: '',
                              start_date: '',
                              end_date: '',
                              grade: '',
                              currently_studying: false,
                              document: null,
                          },
                      ],
            bank_name: user.banking?.bank_name ?? '',
            account_number: user.banking?.account_number ?? '',
            account_type: user.banking?.account_type ?? '',
            branch_name: user.banking?.branch_name ?? '',
            ifsc_code: user.banking?.ifsc_code ?? '',
            address_type: 'local',
            address_line_1: localAddress?.address_line_1 ?? '',
            address_line_2: localAddress?.address_line_2 ?? '',
            city: localAddress?.city ?? '',
            state: localAddress?.state ?? '',
            district: localAddress?.district ?? '',
            postal_code: localAddress?.postal_code ?? '',
            country: localAddress?.country ?? 'India',
            latitude: localAddress?.latitude ? String(localAddress.latitude) : '',
            longitude: localAddress?.longitude ? String(localAddress.longitude) : '',
            permanent_same_as_local: Boolean(
                localAddress &&
                    permanentAddress &&
                    localAddress.address_line_1 === permanentAddress.address_line_1 &&
                    localAddress.postal_code === permanentAddress.postal_code,
            ),
            permanent_address_line_1: permanentAddress?.address_line_1 ?? '',
            permanent_address_line_2: permanentAddress?.address_line_2 ?? '',
            permanent_city: permanentAddress?.city ?? '',
            permanent_state: permanentAddress?.state ?? '',
            permanent_district: permanentAddress?.district ?? '',
            permanent_postal_code: permanentAddress?.postal_code ?? '',
            permanent_country: permanentAddress?.country ?? 'India',
            permanent_latitude: permanentAddress?.latitude ? String(permanentAddress.latitude) : '',
            permanent_longitude: permanentAddress?.longitude ? String(permanentAddress.longitude) : '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!explicitSubmitRef.current) return;
        explicitSubmitRef.current = false;
        form.transform((data) => ({
            ...data,
            date_of_birth: dateForDatabase(data.date_of_birth),
            anniversary_date: dateForDatabase(data.anniversary_date),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
            onError: (errors: Partial<Record<keyof UserForm, string>>) => {
                if (
                    errors.first_name ||
                    errors.last_name ||
                    errors.gender ||
                    errors.date_of_birth ||
                    errors.anniversary_date ||
                    errors.user_type ||
                    errors.unique_id ||
                    errors.marital_status ||
                    errors.company ||
                    errors.sitting_location ||
                    errors.department_id ||
                    errors.designation_id ||
                    errors.role ||
                    errors.aadhaar_number ||
                    errors.aadhaar_image ||
                    errors.pan_number ||
                    errors.pan_image
                )
                    setFormStep(0);
                else if (Object.keys(errors).some((key) => key.startsWith('contacts'))) setFormStep(1);
                else if (Object.keys(errors).some((key) => key.startsWith('educations'))) setFormStep(3);
                else if (
                    errors.bank_name ||
                    errors.account_number ||
                    errors.account_type ||
                    errors.branch_name ||
                    errors.ifsc_code
                )
                    setFormStep(4);
                else if (errors.username || errors.password) setFormStep(5);
                else if (
                    errors.address_line_1 ||
                    errors.address_line_2 ||
                    errors.city ||
                    errors.state ||
                    errors.district ||
                    errors.postal_code ||
                    errors.country
                )
                    setFormStep(2);
            },
        };
        if (editingUser) form.put(`/admin/user-master/${editingUser.id}`, options);
        else form.post('/admin/user-master', options);
    };

    const nextFormStep = () => {
        if (formStep === 0) {
            form.clearErrors('first_name', 'last_name', 'user_type', 'role');
            if (!hasText(form.data.first_name)) form.setError('first_name', 'First name is required.');
            if (!hasText(form.data.last_name)) form.setError('last_name', 'Last name is required.');
            if (!form.data.user_type) form.setError('user_type', 'Select a user type.');
            if (!form.data.role) form.setError('role', 'Select a role.');
            if (!hasText(form.data.first_name) || !hasText(form.data.last_name) || !form.data.user_type || !form.data.role) return;
        }

        if (formStep === 1) {
            form.clearErrors('email');
            if (!editingUser && !hasText(form.data.email)) form.setError('email', 'Email is required.');
            if (!editingUser && !hasText(form.data.email)) return;
        }

        if (formStep === 5) {
            form.clearErrors('username', 'password');
            if (!editingUser && !hasText(form.data.username)) form.setError('username', 'Username or email is required.');
            if (!editingUser && !form.data.password) form.setError('password', 'Password is required.');
            if (!editingUser && (!hasText(form.data.username) || !form.data.password)) return;
        }

        setFormStep((current) => Math.min(current + 1, formSteps.length - 1));
    };

    const removeContact = (contactIndex: number) => {
        const remaining = form.data.contacts.filter((_, index) => index !== contactIndex);
        if (remaining.length === 0) {
            form.setData('contacts', [{ contact_type: 'mobile', contact_value: '', is_primary: true }]);
            return;
        }
        if (!remaining.some((contact) => contact.is_primary)) remaining[0] = { ...remaining[0], is_primary: true };
        form.setData('contacts', remaining);
    };

    const stepCompleted = (index: number) => {
        if (index === 0) return Boolean(hasText(form.data.first_name) && hasText(form.data.last_name) && form.data.user_type && form.data.role);
        if (index === 1) return editingUser ? true : Boolean(hasText(form.data.email) && form.data.contacts.some((contact) => hasText(contact.contact_value)));
        if (index === 2)
            return Boolean(
                hasText(form.data.address_line_1) &&
                    form.data.state &&
                    form.data.district &&
                    form.data.city &&
                    form.data.postal_code,
            );
        if (index === 3)
            return form.data.educations.some((education) =>
                Boolean(hasText(education.education_level) || hasText(education.degree_name) || hasText(education.institution_name)),
            );
        if (index === 4) return Boolean(hasText(form.data.bank_name) && hasText(form.data.account_number));
        if (index === 5) return editingUser ? true : Boolean(hasText(form.data.username) && form.data.password);
        return false;
    };

    const setPrimaryContact = (contactIndex: number) => {
        form.setData(
            'contacts',
            form.data.contacts.map((contact, index) => ({ ...contact, is_primary: index === contactIndex })),
        );
    };

    const remove = (user: MasterUser) => {
        if (window.confirm(`Delete ${user.name}? This action cannot be undone.`))
            router.delete(`/admin/user-master/${user.id}`, { preserveScroll: true });
    };
    const sendVerificationOtp = (channel: 'email' | 'phone', contactId: number | null = null) => {
        if (!editingUser) {
            window.alert('Save the employee first, then verify the contact.');
            return;
        }
        setOtpProcessing(true);
        setOtpError('');
        router.post(
            `/admin/user-master/${editingUser.id}/otp/send`,
            { channel, contact_id: contactId },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setOtpChannel(channel);
                    setOtpContactId(contactId);
                    setOtpCode('');
                    setOtpSeconds(60);
                    setOtpOpen(true);
                },
                onError: (errors) => {
                    setOtpChannel(channel);
                    setOtpContactId(contactId);
                    setOtpOpen(true);
                    setOtpError(String(errors.otp ?? `Unable to send ${channel} OTP.`));
                },
                onFinish: () => setOtpProcessing(false),
            },
        );
    };
    const verifyOtp = () => {
        if (!editingUser || otpCode.length !== 6) {
            setOtpError('Enter the complete 6-digit OTP.');
            return;
        }
        setOtpProcessing(true);
        setOtpError('');
        router.post(
            `/admin/user-master/${editingUser.id}/otp/verify`,
            { channel: otpChannel, contact_id: otpContactId, otp: otpCode },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setEditingUser((user) =>
                        user
                            ? otpChannel === 'email'
                                ? { ...user, primary_email: user.primary_email ? { ...user.primary_email, is_verified: true } : user.primary_email }
                                : {
                                      ...user,
                                      contacts: user.contacts.map((contact) =>
                                          contact.id === otpContactId
                                              ? { ...contact, is_verified: true, phone_verified_at: new Date().toISOString() }
                                              : contact,
                                      ),
                                  }
                            : user,
                    );
                    setOtpOpen(false);
                    router.reload({ only: ['users'] });
                },
                onError: (errors) => setOtpError(String(errors.otp ?? 'OTP verification failed.')),
                onFinish: () => setOtpProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-5 lg:p-6">
                <MasterPageHeader
                    title="Employee Master"
                    description={`${stats.total} employees · ${stats.active} active`}
                    icon={<Users className="size-5" />}
                    action={
                        canManage && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={openCreate}
                                    className="h-9 shrink-0 bg-orange-500 px-4 font-semibold text-white shadow-sm hover:bg-orange-600"
                                >
                                    <Plus className="size-4" /> Add employee
                                </Button>
                            </div>
                        )
                    }
                />

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-900">Directory</h2>
                            <p className="mt-0.5 text-xs text-slate-500">Search, sort, update status, or manage multiple records.</p>
                        </div>
                        <form
                            className="relative w-full md:max-w-sm"
                            onSubmit={(event) => {
                                event.preventDefault();
                                loadTable(search);
                            }}
                        >
                            <Search className="absolute top-3 left-3.5 size-4 text-slate-400" />
                            <Input
                                autoComplete="off"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search Emp ID, name, email or phone"
                                className="h-10 rounded-lg border-slate-300 bg-slate-50 pr-20 pl-10 focus-visible:bg-white"
                            />
                            <button
                                type="submit"
                                className="text-brand-teal hover:bg-brand-aqua/15 absolute top-1.5 right-2 rounded-md px-3 py-1.5 text-xs font-bold"
                            >
                                Search
                            </button>
                        </form>
                    </div>
                    {canManage && selectedIds.length > 0 && (
                        <div className="bg-brand-aqua/10 flex flex-col gap-3 border-b border-cyan-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <span className="text-brand-teal text-xs font-bold">{selectedIds.length} selected</span>
                                <Select value={bulkAction} onValueChange={setBulkAction}>
                                    <SelectTrigger className="h-9 w-full border-slate-300 bg-white sm:w-44">
                                        <SelectValue placeholder="Bulk action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="activate">Mark active</SelectItem>
                                        <SelectItem value="deactivate">Mark inactive</SelectItem>
                                        <SelectItem value="delete">Delete selected</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={!bulkAction || selectedIds.length === 0}
                                    onClick={runBulkAction}
                                    className="bg-white"
                                >
                                    Apply
                                </Button>
                            </div>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setSelectedIds([])} className="text-slate-600">
                                <X className="size-4" /> Clear selection
                            </Button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1280px] text-left text-sm">
                            <thead className="bg-slate-50/90 text-[11px] font-bold tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th className="w-12 px-4 py-3">
                                        <Checkbox
                                            checked={allSelected ? true : selectedIds.length > 0 ? 'indeterminate' : false}
                                            onCheckedChange={(checked) => toggleAll(checked === true)}
                                            aria-label="Select all users on this page"
                                        />
                                    </th>
                                    <DataTableSortHeader
                                        label="S.No."
                                        column="sno"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="w-24 px-3 py-3"
                                    />
                                    <th className="px-4 py-3">Emp ID</th>
                                    <DataTableSortHeader
                                        label="User"
                                        column="name"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="px-5 py-3"
                                    />
                                    <DataTableSortHeader
                                        label="Type"
                                        column="type"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="px-5 py-3"
                                    />
                                    <th className="px-5 py-3">Email</th>
                                    <th className="px-5 py-3">Contact</th>
                                    <DataTableSortHeader
                                        label="Status"
                                        column="status"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="px-5 py-3"
                                    />
                                    <DataTableSortHeader
                                        label="Created"
                                        column="created_at"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="px-5 py-3"
                                    />
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.data.map((user, index) => (
                                    <tr key={user.id} className="group transition-colors hover:bg-cyan-50/30">
                                        <td className="px-4 py-4">
                                            <Checkbox
                                                checked={selectedIds.includes(user.id)}
                                                onCheckedChange={(checked) =>
                                                    setSelectedIds((current) =>
                                                        checked === true
                                                            ? [...new Set([...current, user.id])]
                                                            : current.filter((id) => id !== user.id),
                                                    )
                                                }
                                                aria-label={`Select ${user.name}`}
                                            />
                                        </td>
                                        <td className="px-3 py-4 text-xs font-medium text-slate-500">
                                            {(users.current_page - 1) * users.per_page + index + 1}
                                        </td>
                                        <td className="px-4 py-4 font-mono text-xs font-semibold text-slate-600">{user.unique_id ?? '—'}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                {user.primary_photo ? (
                                                    <img
                                                        src={
                                                            user.primary_photo.file_path.startsWith('http')
                                                                ? user.primary_photo.file_path
                                                                : `/storage/${user.primary_photo.file_path.replace(/^\/+/, '')}`
                                                        }
                                                        alt={user.primary_photo.alt_text ?? user.name}
                                                        className="size-10 rounded-full border border-slate-200 object-cover"
                                                    />
                                                ) : (
                                                    <span className="bg-brand-aqua/15 text-brand-teal flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                                        {`${user.first_name?.[0] ?? ''}${user.last_name?.[0] ?? ''}`.toUpperCase() || 'U'}
                                                    </span>
                                                )}
                                                <div>
                                                    <p className="font-semibold text-slate-800">{user.name}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{user.user_type?.name ?? '—'}</td>
                                        <td className="px-5 py-4">
                                            <p className="flex items-center gap-1 text-xs text-slate-600">
                                                <Mail className="size-3" />
                                                {user.email || '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <p className="flex items-center gap-1 text-xs text-slate-600">
                                                <Phone className="size-3" />
                                                {user.contacts[0]?.contact_number ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <StatusToggle
                                                active={user.status}
                                                disabled={!canManage}
                                                label={user.name}
                                                onChange={() =>
                                                    router.patch(
                                                        `/admin/user-master/${user.id}/status`,
                                                        { active: !user.status },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-5 py-4 text-xs text-slate-500">{formatDate(user.created_at)}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-1">
                                                {canManage && (
                                                    <>
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(user)} title="Edit user">
                                                            <Edit3 className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => remove(user)}
                                                            title="Delete user"
                                                            className="text-red-500 hover:text-red-600"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {users.data.length === 0 && <div className="p-10 text-center text-sm text-slate-500">No users found.</div>}
                    <div className="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-3.5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <span>Show</span>
                            <Select value={String(filters.per_page)} onValueChange={(value) => loadTable(filters.search, Number(value))}>
                                <SelectTrigger className="h-8 w-20 bg-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {pageSizeOptions.map((size) => (
                                        <SelectItem key={size} value={String(size)}>
                                            {size}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <span>
                                entries · Showing {users.from ?? 0}–{users.to ?? 0} of {users.total}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!users.prev_page_url}
                                onClick={() =>
                                    users.prev_page_url && router.get(users.prev_page_url, {}, { preserveState: true, preserveScroll: true })
                                }
                            >
                                <ChevronLeft className="size-4" /> Previous
                            </Button>
                            <span className="px-2 font-semibold text-slate-700">
                                Page {users.current_page} of {users.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!users.next_page_url}
                                onClick={() =>
                                    users.next_page_url && router.get(users.next_page_url, {}, { preserveState: true, preserveScroll: true })
                                }
                            >
                                Next <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </section>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="block h-auto max-h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] max-w-none gap-0 overflow-hidden rounded-xl border border-white/20 bg-slate-50 p-0 shadow-[0_28px_80px_-20px_rgba(15,23,42,0.55)] sm:max-h-[calc(100dvh-3rem)] sm:w-[calc(100vw-3rem)] sm:max-w-[calc(100vw-3rem)] sm:rounded-2xl sm:p-0 xl:max-w-7xl [&>button]:top-4 [&>button]:right-5 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:text-white/70 [&>button]:transition-colors [&>button]:hover:bg-white/10 [&>button]:hover:text-white">
                    <form
                        autoComplete="off"
                        onPointerDownCapture={(event) => {
                            const target = event.target;
                            if (target instanceof HTMLInputElement && target.getAttribute('autocomplete') !== 'one-time-code') {
                                target.setAttribute('autocomplete', 'new-password');
                            }
                        }}
                        onFocusCapture={(event) => {
                            const target = event.target;
                            if (target instanceof HTMLInputElement && target.getAttribute('autocomplete') !== 'one-time-code') {
                                target.setAttribute('autocomplete', 'new-password');
                            }
                        }}
                        onSubmit={submit}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
                                event.preventDefault();
                            }
                        }}
                        className="flex max-h-[calc(100dvh-1rem)] min-h-0 w-full flex-col overflow-hidden sm:max-h-[calc(100dvh-3rem)]"
                    >
                        <input type="hidden" name="user_type" value="USR6" />
                        <input className="hidden" tabIndex={-1} aria-hidden="true" autoComplete="username" />
                        <input className="hidden" tabIndex={-1} aria-hidden="true" type="password" autoComplete="new-password" />
                        <MasterDialogHeader compact title={editingUser ? 'Edit employee' : 'Create employee'} />
                        <div className="shrink-0 border-b border-slate-200 bg-gradient-to-b from-white to-slate-50/70 px-2 py-2.5 sm:px-8 sm:py-3">
                            <ol className="grid grid-cols-6">
                                {formSteps.map((step, index) => (
                                    <li key={step} className="relative">
                                        {index < formSteps.length - 1 && (
                                            <span className="absolute top-[17px] left-1/2 h-[3px] w-full overflow-hidden bg-slate-200">
                                                <span
                                                    className={`bg-brand-teal block h-full origin-left transition-all duration-500 ease-out ${stepCompleted(index) ? 'scale-x-100' : 'scale-x-0'}`}
                                                />
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => (editingUser || index <= formStep) && setFormStep(index)}
                                            aria-current={index === formStep ? 'step' : undefined}
                                            className={`relative z-10 flex w-full flex-col items-center gap-1.5 text-center outline-none ${editingUser || index <= formStep ? 'cursor-pointer' : 'cursor-default'}`}
                                        >
                                            <span
                                                className={`grid size-9 place-items-center rounded-full border-[3px] text-xs font-bold transition-all duration-300 ease-out ${
                                                    stepCompleted(index)
                                                        ? 'bg-brand-teal ring-brand-aqua border-white text-white shadow-[0_4px_12px_rgba(33,158,188,0.3)] ring-2'
                                                        : index === formStep
                                                          ? 'text-brand-teal ring-brand-teal border-white bg-white shadow-[0_4px_14px_rgba(33,158,188,0.22)] ring-2 ring-offset-2 ring-offset-white'
                                                          : 'border-white bg-slate-100 text-slate-400 shadow-sm ring-1 ring-slate-200'
                                                }`}
                                            >
                                                {stepCompleted(index) ? (
                                                    <Check className="animate-in zoom-in size-4 duration-300" strokeWidth={3.25} />
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            <span
                                                className={`hidden max-w-[120px] truncate text-[9px] font-semibold transition-colors duration-300 sm:block sm:text-[11px] ${stepCompleted(index) ? 'text-brand-teal' : index === formStep ? 'text-brand-plum' : 'text-slate-400'}`}
                                            >
                                                {step}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ol>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pt-2 pb-6 sm:px-6 sm:pt-3 sm:pb-7">
                            {formStep === 0 && (
                                <div className="grid gap-4 pb-3">
                                    <fieldset className="form-fieldset !m-0 !px-4 !pb-3">
                                        <legend>Personal information</legend>
                                        <div className="grid gap-x-3 gap-y-3 pt-2 sm:grid-cols-3">
                                            <Field label="First name" error={form.errors.first_name}>
                                                <Input
                                                    autoFocus
                                                    placeholder="First name"
                                                    value={form.data.first_name}
                                                    onChange={(e) => form.setData('first_name', e.target.value)}
                                                />
                                            </Field>
                                            <Field label="Middle name" error={form.errors.middle_name}>
                                                <Input
                                                    placeholder="Middle name"
                                                    value={form.data.middle_name}
                                                    onChange={(e) => form.setData('middle_name', e.target.value)}
                                                />
                                            </Field>
                                            <Field label="Last name" error={form.errors.last_name}>
                                                <Input
                                                    placeholder="Last name"
                                                    value={form.data.last_name}
                                                    onChange={(e) => form.setData('last_name', e.target.value)}
                                                />
                                            </Field>
                                            <Field label="Gender" error={form.errors.gender}>
                                                <SearchableSelect
                                                    value={form.data.gender}
                                                    onValueChange={(value) => form.setData('gender', value)}
                                                    placeholder="Select gender"
                                                    options={[
                                                        { value: 'male', label: 'Male' },
                                                        { value: 'female', label: 'Female' },
                                                        { value: 'other', label: 'Other' },
                                                    ]}
                                                />
                                            </Field>
                                            <Field label="Marital status" error={form.errors.marital_status}>
                                                <SearchableSelect
                                                    value={form.data.marital_status}
                                                    onValueChange={(value) => form.setData('marital_status', value)}
                                                    placeholder="Select marital status"
                                                    options={[
                                                        { value: 'single', label: 'Single' },
                                                        { value: 'married', label: 'Married' },
                                                        { value: 'divorced', label: 'Divorced' },
                                                        { value: 'widowed', label: 'Widowed' },
                                                    ]}
                                                />
                                            </Field>
                                            <Field label="Date of birth" error={form.errors.date_of_birth}>
                                                <DatePickerInput
                                                    value={form.data.date_of_birth}
                                                    onChange={(value) => form.setData('date_of_birth', value)}
                                                    placeholder="Date of birth"
                                                    max={new Date().toISOString().slice(0, 10)}
                                                />
                                            </Field>
                                            <Field label="Anniversary date" error={form.errors.anniversary_date}>
                                                <DatePickerInput
                                                    value={form.data.anniversary_date}
                                                    onChange={(value) => form.setData('anniversary_date', value)}
                                                    placeholder="Anniversary date"
                                                />
                                            </Field>
                                        </div>
                                    </fieldset>
                                    <fieldset className="form-fieldset !m-0 !px-4 !pb-3">
                                        <legend>Employment information</legend>
                                        <div className="grid gap-x-3 gap-y-3 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                                            <Field label="Employee ID" error={form.errors.unique_id}>
                                                <Input
                                                    value={form.data.unique_id}
                                                    onChange={(event) =>
                                                        form.setData('unique_id', event.target.value.trimStart().toUpperCase().slice(0, 20))
                                                    }
                                                    placeholder="Enter employee ID"
                                                    maxLength={20}
                                                    autoComplete="off"
                                                />
                                            </Field>
                                            <Field label="Company" error={form.errors.company}>
                                                <SearchableSelect
                                                    value={form.data.company}
                                                    onValueChange={(value) => {
                                                        form.setData('company', value);
                                                        const selectedCompany = companies.find(
                                                            (company) => company.company_name === value || company.company_code === value,
                                                        );
                                                        const currentPlant = sittingLocations.find(
                                                            (plant) =>
                                                                plant.company_name === form.data.sitting_location ||
                                                                plant.company_code === form.data.sitting_location,
                                                        );
                                                        if (
                                                            currentPlant &&
                                                            selectedCompany &&
                                                            currentPlant.parent_company_id !== selectedCompany.company_code
                                                        )
                                                            form.setData('sitting_location', '');
                                                    }}
                                                    placeholder="Select company"
                                                    fieldLabel="Company"
                                                    options={companies.map((company) => ({
                                                        value: company.company_name,
                                                        label: `${company.company_name} (${company.company_code})`,
                                                    }))}
                                                />
                                            </Field>
                                            <Field label="Sitting location" error={form.errors.sitting_location}>
                                                <SearchableSelect
                                                    value={form.data.sitting_location}
                                                    onValueChange={(value) => form.setData('sitting_location', value)}
                                                    placeholder="Select plant or head office"
                                                    fieldLabel="Sitting location"
                                                    options={sittingLocations
                                                        .filter((plant) => {
                                                            const selectedCompany = companies.find(
                                                                (company) =>
                                                                    company.company_name === form.data.company ||
                                                                    company.company_code === form.data.company,
                                                            );
                                                            return !selectedCompany || plant.parent_company_id === selectedCompany.company_code;
                                                        })
                                                        .map((plant) => ({
                                                            value: plant.company_name,
                                                            label: `${plant.company_name} (${plant.company_code}) · ${plant.record_type === 3 ? 'Head Office' : 'Plant'}`,
                                                        }))}
                                                />
                                            </Field>
                                            <Field label="Department" error={form.errors.department_id}>
                                                <SearchableSelect
                                                    value={form.data.department_id}
                                                    onValueChange={(value) => {
                                                        form.setData('department_id', value);
                                                        const selectedDesignation = designations.find(
                                                            (item) => String(item.id) === form.data.designation_id,
                                                        );
                                                        if (selectedDesignation?.department_id && String(selectedDesignation.department_id) !== value)
                                                            form.setData('designation_id', '');
                                                    }}
                                                    placeholder="Select department"
                                                    options={departments.map((department) => ({
                                                        value: String(department.id),
                                                        label: department.department_name,
                                                    }))}
                                                />
                                            </Field>
                                            <Field label="Designation" error={form.errors.designation_id}>
                                                <SearchableSelect
                                                    value={form.data.designation_id}
                                                    onValueChange={(value) => form.setData('designation_id', value)}
                                                    placeholder="Select designation"
                                                    options={designations
                                                        .filter(
                                                            (designation) =>
                                                                !form.data.department_id ||
                                                                !designation.department_id ||
                                                                String(designation.department_id) === form.data.department_id,
                                                        )
                                                        .map((designation) => ({
                                                            value: String(designation.id),
                                                            label: designation.designation_name,
                                                        }))}
                                                />
                                            </Field>
                                            <Field label="Role" error={form.errors.role}>
                                                <SearchableSelect
                                                    value={form.data.role}
                                                    onValueChange={(value) => form.setData('role', value)}
                                                    placeholder="Select role"
                                                    options={roles.map((role) => ({ value: role.name, label: role.name }))}
                                                />
                                            </Field>
                                        </div>
                                    </fieldset>
                                    <fieldset className="form-fieldset !m-0 !px-4 !pb-3">
                                        <legend>Identity documents</legend>
                                        <p className="mt-1.5 text-xs text-slate-500">
                                            Upload a clear Aadhaar or PAN image to read the number automatically. You can also enter the number
                                            manually.
                                        </p>
                                        <div className="grid gap-3 pt-2 sm:grid-cols-2">
                                            <div className="space-y-4 rounded-lg border border-slate-200 bg-white p-3">
                                                <Field label="Aadhaar number" error={form.errors.aadhaar_number}>
                                                    <Input
                                                        inputMode="numeric"
                                                        maxLength={12}
                                                        placeholder="Aadhaar number"
                                                        value={form.data.aadhaar_number}
                                                        onChange={(event) =>
                                                            form.setData('aadhaar_number', event.target.value.replace(/\D/g, '').slice(0, 12))
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Aadhaar image" error={form.errors.aadhaar_image}>
                                                    <Input
                                                        id="aadhaar-image-upload"
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        disabled={readingDocument !== null}
                                                        className="sr-only"
                                                        onChange={(event) => void readIdentityDocument('aadhaar', event.target.files?.[0] ?? null)}
                                                    />
                                                    <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5">
                                                        <span className="min-w-0 flex-1 truncate text-sm text-slate-500">
                                                            {form.data.aadhaar_image?.name ?? 'No file selected'}
                                                        </span>
                                                        <Label
                                                            htmlFor="aadhaar-image-upload"
                                                            className={`bg-brand-teal inline-flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-3 text-xs font-semibold text-white ${readingDocument !== null ? 'pointer-events-none opacity-50' : 'hover:bg-brand-plum'}`}
                                                        >
                                                            <Upload className="size-3.5" /> Upload
                                                        </Label>
                                                    </div>
                                                </Field>
                                                <p className="text-xs text-slate-500">
                                                    {readingDocument === 'aadhaar' ? 'Reading Aadhaar image…' : 'JPG, PNG or WebP, up to 10 MB.'}
                                                </p>
                                            </div>
                                            <div className="space-y-4 rounded-lg border border-slate-200 bg-white p-3">
                                                <Field label="PAN number" error={form.errors.pan_number}>
                                                    <Input
                                                        maxLength={10}
                                                        placeholder="PAN number"
                                                        value={form.data.pan_number}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'pan_number',
                                                                event.target.value
                                                                    .toUpperCase()
                                                                    .replace(/[^A-Z0-9]/g, '')
                                                                    .slice(0, 10),
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field label="PAN image" error={form.errors.pan_image}>
                                                    <Input
                                                        id="pan-image-upload"
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        disabled={readingDocument !== null}
                                                        className="sr-only"
                                                        onChange={(event) => void readIdentityDocument('pan', event.target.files?.[0] ?? null)}
                                                    />
                                                    <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5">
                                                        <span className="min-w-0 flex-1 truncate text-sm text-slate-500">
                                                            {form.data.pan_image?.name ?? 'No file selected'}
                                                        </span>
                                                        <Label
                                                            htmlFor="pan-image-upload"
                                                            className={`bg-brand-teal inline-flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-3 text-xs font-semibold text-white ${readingDocument !== null ? 'pointer-events-none opacity-50' : 'hover:bg-brand-plum'}`}
                                                        >
                                                            <Upload className="size-3.5" /> Upload
                                                        </Label>
                                                    </div>
                                                </Field>
                                                <p className="text-xs text-slate-500">
                                                    {readingDocument === 'pan' ? 'Reading PAN image…' : 'JPG, PNG or WebP, up to 10 MB.'}
                                                </p>
                                            </div>
                                        </div>
                                    </fieldset>
                                    <fieldset className="hidden">
                                        <legend>Contact information</legend>
                                        <div className="grid gap-4 pt-4 sm:grid-cols-2">
                                            <div className="space-y-4 sm:col-span-2">
                                                {form.data.contacts.map((contact, contactIndex) => {
                                                    const contactErrors = form.errors as Record<string, string>;

                                                    return (
                                                        <div
                                                            key={contact.id ?? contactIndex}
                                                            className="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[180px_1fr_auto] sm:items-start"
                                                        >
                                                            <Field
                                                                label={`Contact type ${contactIndex + 1}`}
                                                                error={contactErrors[`contacts.${contactIndex}.contact_type`]}
                                                            >
                                                                <Select
                                                                    value={contact.contact_type}
                                                                    onValueChange={(value) =>
                                                                        form.setData(
                                                                            'contacts',
                                                                            form.data.contacts.map((item, index) =>
                                                                                index === contactIndex ? { ...item, contact_type: value } : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="mobile">Mobile</SelectItem>
                                                                        <SelectItem value="email">Email</SelectItem>
                                                                        <SelectItem value="phone">Phone</SelectItem>
                                                                        <SelectItem value="office">Office</SelectItem>
                                                                        <SelectItem value="other">Other</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </Field>
                                                            <Field
                                                                label={`${contact.contact_type === 'email' ? 'Email address' : 'Contact number'} ${contactIndex + 1}`}
                                                                error={contactErrors[`contacts.${contactIndex}.contact_value`]}
                                                            >
                                                                <Input
                                                                    type={contact.contact_type === 'email' ? 'email' : 'text'}
                                                                    inputMode={contact.contact_type === 'email' ? 'email' : 'tel'}
                                                                    placeholder={
                                                                        contact.contact_type === 'email' ? 'Email address' : 'Contact number'
                                                                    }
                                                                    value={contact.contact_value}
                                                                    onBlur={(event) => {
                                                                        if (event.target.value.includes('@') && contact.contact_type !== 'email')
                                                                            form.setData(
                                                                                'contacts',
                                                                                form.data.contacts.map((item, index) =>
                                                                                    index === contactIndex
                                                                                        ? { ...item, contact_type: 'email' }
                                                                                        : item,
                                                                                ),
                                                                            );
                                                                    }}
                                                                    onChange={(event) =>
                                                                        form.setData(
                                                                            'contacts',
                                                                            form.data.contacts.map((item, index) =>
                                                                                index === contactIndex
                                                                                    ? { ...item, contact_value: event.target.value }
                                                                                    : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                            <label className="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium whitespace-nowrap text-slate-700">
                                                                <Checkbox
                                                                    checked={contact.is_primary}
                                                                    onCheckedChange={(checked) => {
                                                                        if (!checked) return;
                                                                        form.setData(
                                                                            'contacts',
                                                                            form.data.contacts.map((item, index) => ({
                                                                                ...item,
                                                                                is_primary: index === contactIndex,
                                                                            })),
                                                                        );
                                                                    }}
                                                                />
                                                                Set as primary
                                                            </label>
                                                        </div>
                                                    );
                                                })}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="w-full border-dashed sm:w-auto"
                                                    disabled={form.data.contacts.length >= 10}
                                                    onClick={() =>
                                                        form.setData('contacts', [
                                                            ...form.data.contacts,
                                                            { contact_type: 'mobile', contact_value: '', is_primary: false },
                                                        ])
                                                    }
                                                >
                                                    <Plus className="size-4" /> Add more contact
                                                </Button>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            )}

                            {formStep === 1 && (
                                <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <div className="mb-4">
                                        <h3 className="font-semibold text-slate-900">Contact details</h3>
                                        <p className="mt-1 text-sm text-slate-500">Add the employee email and one or more contact numbers.</p>
                                    </div>

                                    <div className="flex flex-col">
                                        <div className="order-2 mb-4 overflow-hidden rounded-xl border border-cyan-300">
                                            <div className="flex flex-wrap items-center justify-between gap-2 bg-cyan-50 px-4 py-2.5">
                                                <div className="flex min-w-0 items-center gap-3 text-sm font-semibold">
                                                    <span>Email</span>
                                                </div>
                                                <span className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                    <span className="bg-brand-teal relative h-5 w-9 rounded-full">
                                                        <span className="absolute top-0.5 left-[18px] size-4 rounded-full bg-white shadow" />
                                                    </span>
                                                    Primary contact
                                                </span>
                                            </div>
                                            <div className="grid gap-4 p-4 sm:grid-cols-2">
                                                <Field label="Contact type">
                                                    <SearchableSelect
                                                        value="email"
                                                        onValueChange={() => undefined}
                                                        placeholder="Email"
                                                        options={[{ value: 'email', label: 'Email' }]}
                                                    />
                                                </Field>
                                                <Field label="Email address" error={form.errors.email}>
                                                    <Input
                                                        id="primary-email-field"
                                                        type="email"
                                                        inputMode="email"
                                                        placeholder="Email address"
                                                        value={form.data.email}
                                                        onChange={(event) => form.setData('email', event.target.value)}
                                                    />
                                                </Field>
                                            </div>
                                        </div>

                                        {(hasText(form.data.email) || form.data.contacts.some((contact) => hasText(contact.contact_value))) && (
                                            <div className="order-1 mb-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                                                <table className="w-full min-w-[760px] text-left text-sm">
                                                    <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-[0.08em] text-slate-600 uppercase">
                                                        <tr>
                                                            <th className="w-14 px-4 py-3">#</th>
                                                            <th className="w-36 px-4 py-3">Type</th>
                                                            <th className="px-4 py-3">Contact</th>
                                                            <th className="w-28 px-4 py-3">Primary</th>
                                                            <th className="w-[42%] px-4 py-3">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-200">
                                                        {hasText(form.data.email) && (
                                                            <tr className="transition-colors hover:bg-slate-50/70">
                                                                <td className="px-4 py-3.5 text-slate-500">1</td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className="bg-brand-aqua/15 text-brand-teal rounded-full px-2.5 py-1 text-xs font-semibold">
                                                                        Email
                                                                    </span>
                                                                </td>
                                                                <td className="px-4 py-3.5 font-medium text-slate-800">{form.data.email}</td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                                        Yes
                                                                    </span>
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <button
                                                                            type="button"
                                                                            className="text-brand-teal hover:bg-brand-aqua/25 rounded-full bg-cyan-50 px-3 py-1.5 text-xs font-semibold transition"
                                                                            onClick={() => document.getElementById('primary-email-field')?.focus()}
                                                                        >
                                                                            Edit
                                                                        </button>
                                                                        {!otpOpen || otpChannel !== 'email' ? (
                                                                            <button
                                                                                type="button"
                                                                                className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${editingUser?.primary_email?.email_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'}`}
                                                                                onClick={() => sendVerificationOtp('email')}
                                                                                disabled={Boolean(editingUser?.primary_email?.email_verified_at)}
                                                                            >
                                                                                {editingUser?.primary_email?.email_verified_at
                                                                                    ? 'Verified'
                                                                                    : 'Send email OTP'}
                                                                            </button>
                                                                        ) : (
                                                                            <span className="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700">
                                                                                Code sent
                                                                            </span>
                                                                        )}
                                                                        {otpOpen && otpChannel === 'email' && (
                                                                            <div className="contents">
                                                                                <Input
                                                                                    autoFocus
                                                                                    inputMode="numeric"
                                                                                    autoComplete="one-time-code"
                                                                                    maxLength={6}
                                                                                    aria-label="Email verification OTP"
                                                                                    className="h-9 w-36 border-orange-200 bg-white font-mono tracking-[0.2em]"
                                                                                    placeholder="6-digit OTP"
                                                                                    value={otpCode}
                                                                                    onChange={(event) =>
                                                                                        setOtpCode(event.target.value.replace(/\D/g, '').slice(0, 6))
                                                                                    }
                                                                                    onKeyDown={(event) => event.key === 'Enter' && verifyOtp()}
                                                                                />
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    className="h-9 bg-orange-500 px-4 font-semibold text-white hover:bg-orange-600"
                                                                                    disabled={otpProcessing || otpCode.length !== 6}
                                                                                    onClick={verifyOtp}
                                                                                >
                                                                                    {otpProcessing ? 'Verifying…' : 'Verify'}
                                                                                </Button>
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    className="h-9 border-orange-200 bg-white"
                                                                                    disabled={otpSeconds > 0 || otpProcessing}
                                                                                    onClick={() => sendVerificationOtp('email')}
                                                                                >
                                                                                    {otpSeconds > 0 ? `${otpSeconds}s` : 'Resend'}
                                                                                </Button>
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="ghost"
                                                                                    className="h-9 text-slate-600"
                                                                                    onClick={() => setOtpOpen(false)}
                                                                                >
                                                                                    Cancel
                                                                                </Button>
                                                                                {otpError && (
                                                                                    <span className="basis-full rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700">
                                                                                        {otpError}
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        )}
                                                        {form.data.contacts.map(
                                                            (contact, index) =>
                                                                hasText(contact.contact_value) && (
                                                                    <tr
                                                                        key={`summary-${contact.id ?? index}`}
                                                                        className="transition-colors hover:bg-slate-50/70"
                                                                    >
                                                                        <td className="px-4 py-3.5 text-slate-500">{index + 2}</td>
                                                                        <td className="px-4 py-3.5">
                                                                            <span className="bg-brand-aqua/15 text-brand-teal rounded-full px-2.5 py-1 text-xs font-semibold capitalize">
                                                                                {contact.contact_type}
                                                                            </span>
                                                                        </td>
                                                                        <td className="px-4 py-3.5 font-medium text-slate-800">
                                                                            {contact.contact_value}
                                                                        </td>
                                                                        <td className="px-4 py-3.5">
                                                                            <span
                                                                                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${contact.is_primary ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                                                            >
                                                                                {contact.is_primary ? 'Yes' : 'No'}
                                                                            </span>
                                                                        </td>
                                                                        <td className="px-4 py-3.5">
                                                                            <div className="flex flex-wrap items-center gap-2">
                                                                                <button
                                                                                    type="button"
                                                                                    className="text-brand-teal hover:bg-brand-aqua/25 rounded-full bg-cyan-50 px-3 py-1.5 text-xs font-semibold transition"
                                                                                    onClick={() =>
                                                                                        document
                                                                                            .getElementById(`contact-card-${index}`)
                                                                                            ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                                                                                    }
                                                                                >
                                                                                    Edit
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    className="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
                                                                                    onClick={() => removeContact(index)}
                                                                                >
                                                                                    Remove
                                                                                </button>
                                                                                {contact.contact_type !== 'email' &&
                                                                                    (!otpOpen ||
                                                                                        otpChannel !== 'phone' ||
                                                                                        otpContactId !== (contact.id ?? null)) && (
                                                                                        <button
                                                                                            type="button"
                                                                                            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${editingUser?.contacts.find((item) => item.id === contact.id)?.phone_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'}`}
                                                                                            onClick={() =>
                                                                                                sendVerificationOtp('phone', contact.id ?? null)
                                                                                            }
                                                                                            disabled={Boolean(
                                                                                                editingUser?.contacts.find(
                                                                                                    (item) => item.id === contact.id,
                                                                                                )?.phone_verified_at,
                                                                                            )}
                                                                                        >
                                                                                            {editingUser?.contacts.find(
                                                                                                (item) => item.id === contact.id,
                                                                                            )?.phone_verified_at
                                                                                                ? 'Verified'
                                                                                                : 'Send phone OTP'}
                                                                                        </button>
                                                                                    )}
                                                                                {otpOpen &&
                                                                                    otpChannel === 'phone' &&
                                                                                    otpContactId === (contact.id ?? null) && (
                                                                                        <span className="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700">
                                                                                            Code sent
                                                                                        </span>
                                                                                    )}
                                                                                {otpOpen &&
                                                                                    otpChannel === 'phone' &&
                                                                                    otpContactId === (contact.id ?? null) && (
                                                                                        <div className="contents">
                                                                                            <Input
                                                                                                autoFocus
                                                                                                inputMode="numeric"
                                                                                                autoComplete="one-time-code"
                                                                                                maxLength={6}
                                                                                                aria-label="Phone verification OTP"
                                                                                                className="h-9 w-36 border-orange-200 bg-white font-mono tracking-[0.2em]"
                                                                                                placeholder="6-digit OTP"
                                                                                                value={otpCode}
                                                                                                onChange={(event) =>
                                                                                                    setOtpCode(
                                                                                                        event.target.value
                                                                                                            .replace(/\D/g, '')
                                                                                                            .slice(0, 6),
                                                                                                    )
                                                                                                }
                                                                                                onKeyDown={(event) =>
                                                                                                    event.key === 'Enter' && verifyOtp()
                                                                                                }
                                                                                            />
                                                                                            <Button
                                                                                                type="button"
                                                                                                size="sm"
                                                                                                className="h-9 bg-orange-500 px-4 font-semibold text-white hover:bg-orange-600"
                                                                                                disabled={otpProcessing || otpCode.length !== 6}
                                                                                                onClick={verifyOtp}
                                                                                            >
                                                                                                {otpProcessing ? 'Verifying…' : 'Verify'}
                                                                                            </Button>
                                                                                            <Button
                                                                                                type="button"
                                                                                                size="sm"
                                                                                                variant="outline"
                                                                                                className="h-9 border-orange-200 bg-white"
                                                                                                disabled={otpSeconds > 0 || otpProcessing}
                                                                                                onClick={() =>
                                                                                                    sendVerificationOtp('phone', contact.id ?? null)
                                                                                                }
                                                                                            >
                                                                                                {otpSeconds > 0 ? `${otpSeconds}s` : 'Resend'}
                                                                                            </Button>
                                                                                            <Button
                                                                                                type="button"
                                                                                                size="sm"
                                                                                                variant="ghost"
                                                                                                className="h-9 text-slate-600"
                                                                                                onClick={() => setOtpOpen(false)}
                                                                                            >
                                                                                                Cancel
                                                                                            </Button>
                                                                                            {otpError && (
                                                                                                <span className="basis-full rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700">
                                                                                                    {otpError}
                                                                                                </span>
                                                                                            )}
                                                                                        </div>
                                                                                    )}
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>

                                    <div className="space-y-4">
                                        {form.data.contacts.map((contact, contactIndex) => {
                                            const contactErrors = form.errors as Record<string, string>;
                                            return (
                                                <div
                                                    id={`contact-card-${contactIndex}`}
                                                    key={contact.id ?? contactIndex}
                                                    className={`overflow-hidden rounded-xl border ${contact.is_primary ? 'border-cyan-300' : 'border-slate-200'}`}
                                                >
                                                    <div
                                                        className={`flex items-center justify-between px-4 py-2.5 ${contact.is_primary ? 'bg-cyan-50' : 'bg-slate-50'}`}
                                                    >
                                                        <div className="flex items-center gap-3 text-sm font-semibold">
                                                            <span className="text-brand-teal text-[10px] font-bold tracking-wider uppercase">
                                                                Contact {contactIndex + 1}
                                                            </span>
                                                            <span className="capitalize">{contact.contact_type}</span>
                                                            {hasText(contact.contact_value) && (
                                                                <span className="max-w-[320px] truncate font-normal text-slate-600">
                                                                    {contact.contact_value}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <button
                                                            type="button"
                                                            className="flex items-center gap-2 text-xs font-semibold text-slate-700"
                                                            onClick={() => setPrimaryContact(contactIndex)}
                                                        >
                                                            <span
                                                                className={`relative h-5 w-9 rounded-full transition ${contact.is_primary ? 'bg-brand-teal' : 'bg-slate-300'}`}
                                                            >
                                                                <span
                                                                    className={`absolute top-0.5 size-4 rounded-full bg-white shadow transition ${contact.is_primary ? 'left-[18px]' : 'left-0.5'}`}
                                                                />
                                                            </span>
                                                            Primary contact
                                                        </button>
                                                    </div>
                                                    <div className="grid gap-4 p-4 sm:grid-cols-2">
                                                        <Field
                                                            label={`Contact type ${contactIndex + 1}`}
                                                            error={contactErrors[`contacts.${contactIndex}.contact_type`]}
                                                        >
                                                            <SearchableSelect
                                                                value={contact.contact_type}
                                                                onValueChange={(value) =>
                                                                    form.setData(
                                                                        'contacts',
                                                                        form.data.contacts.map((item, index) =>
                                                                            index === contactIndex ? { ...item, contact_type: value } : item,
                                                                        ),
                                                                    )
                                                                }
                                                                placeholder="Select contact type"
                                                                options={[
                                                                    { value: 'email', label: 'Email' },
                                                                    { value: 'mobile', label: 'Mobile' },
                                                                    { value: 'phone', label: 'Telephone' },
                                                                    { value: 'office', label: 'Office' },
                                                                    { value: 'other', label: 'Other' },
                                                                ]}
                                                            />
                                                        </Field>
                                                        <Field
                                                            label={`Contact value ${contactIndex + 1}`}
                                                            error={contactErrors[`contacts.${contactIndex}.contact_value`]}
                                                        >
                                                            <Input
                                                                type={contact.contact_type === 'email' ? 'email' : 'text'}
                                                                inputMode={contact.contact_type === 'email' ? 'email' : 'tel'}
                                                                placeholder={contact.contact_type === 'email' ? 'Email address' : 'Contact number'}
                                                                value={contact.contact_value}
                                                                onBlur={(event) => {
                                                                    if (event.target.value.includes('@') && contact.contact_type !== 'email')
                                                                        form.setData(
                                                                            'contacts',
                                                                            form.data.contacts.map((item, index) =>
                                                                                index === contactIndex ? { ...item, contact_type: 'email' } : item,
                                                                            ),
                                                                        );
                                                                }}
                                                                onChange={(event) =>
                                                                    form.setData(
                                                                        'contacts',
                                                                        form.data.contacts.map((item, index) =>
                                                                            index === contactIndex
                                                                                ? { ...item, contact_value: event.target.value }
                                                                                : item,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="border-slate-300 bg-white"
                                                disabled={form.data.contacts.length >= 10}
                                                onClick={() =>
                                                    form.setData('contacts', [
                                                        ...form.data.contacts,
                                                        { contact_type: 'mobile', contact_value: '', is_primary: false },
                                                    ])
                                                }
                                            >
                                                <Plus className="size-4" /> Add contact number
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="border-slate-300 bg-white"
                                                disabled={form.data.contacts.length >= 10}
                                                onClick={() =>
                                                    form.setData('contacts', [
                                                        ...form.data.contacts,
                                                        { contact_type: 'email', contact_value: '', is_primary: false },
                                                    ])
                                                }
                                            >
                                                <Mail className="size-4" /> Add email
                                            </Button>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {formStep === 5 && (
                                <fieldset className="form-fieldset !m-0">
                                    <legend>Login information</legend>
                                    <div className="grid gap-4 pt-3 sm:grid-cols-2">
                                        <Field label="Username or email" error={form.errors.username}>
                                            <Input
                                                autoFocus
                                                type="text"
                                                autoComplete="username"
                                                placeholder="Username or email"
                                                value={form.data.username}
                                                onChange={(e) => form.setData('username', e.target.value)}
                                            />
                                        </Field>
                                        <Field label={editingUser ? 'New password (optional)' : 'Password'} error={form.errors.password}>
                                            <Input
                                                type="password"
                                                placeholder={editingUser ? 'New password (optional)' : 'Password'}
                                                value={form.data.password}
                                                onChange={(e) => form.setData('password', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </fieldset>
                            )}

                            {formStep === 2 && (
                                <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <label className="mb-4 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                        <Checkbox
                                            checked={form.data.permanent_same_as_local}
                                            onCheckedChange={(checked) => {
                                                form.setData('permanent_same_as_local', checked === true);
                                                if (checked === true) setActiveAddress('local');
                                            }}
                                        />
                                        Same as local address
                                    </label>
                                    <div className="mb-4 flex gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                                        <button
                                            type="button"
                                            onClick={() => setActiveAddress('local')}
                                            className={`min-w-36 rounded-lg px-4 py-2 text-left transition ${activeAddress === 'local' ? 'border-brand-teal text-brand-plum border bg-white shadow-sm' : 'text-slate-500 hover:bg-white/70'}`}
                                        >
                                            <span className="text-brand-teal block text-[10px] font-bold tracking-wider uppercase">Address 1</span>
                                            <span className="block text-sm font-semibold">Current / Local</span>
                                            <span className="block max-w-44 truncate text-xs">
                                                {[form.data.city, form.data.postal_code].filter(Boolean).join(' · ') || 'Not completed'}
                                            </span>
                                        </button>
                                        {!form.data.permanent_same_as_local && (
                                            <button
                                                type="button"
                                                onClick={() => setActiveAddress('permanent')}
                                                className={`min-w-36 rounded-lg px-4 py-2 text-left transition ${activeAddress === 'permanent' ? 'border-brand-teal text-brand-plum border bg-white shadow-sm' : 'text-slate-500 hover:bg-white/70'}`}
                                            >
                                                <span className="text-brand-teal block text-[10px] font-bold tracking-wider uppercase">
                                                    Address 2
                                                </span>
                                                <span className="block text-sm font-semibold">Permanent</span>
                                                <span className="block max-w-44 truncate text-xs">
                                                    {[form.data.permanent_city, form.data.permanent_postal_code].filter(Boolean).join(' · ') ||
                                                        'Not completed'}
                                                </span>
                                            </button>
                                        )}
                                    </div>
                                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.55fr)]">
                                        <fieldset className={`form-fieldset !m-0 ${activeAddress === 'local' ? '' : 'hidden'}`}>
                                            <legend>Local address</legend>
                                            <div className="grid gap-4 pt-3 sm:grid-cols-2">
                                                <Field label="Address line 1" error={form.errors.address_line_1}>
                                                    <Input
                                                        autoFocus
                                                        autoComplete="new-password"
                                                        data-form-type="other"
                                                        placeholder="Address line 1"
                                                        value={form.data.address_line_1}
                                                        onChange={(e) => form.setData('address_line_1', e.target.value)}
                                                    />
                                                </Field>
                                                <Field label="Address line 2" error={form.errors.address_line_2}>
                                                    <Input
                                                        autoComplete="new-password"
                                                        data-form-type="other"
                                                        placeholder="Address line 2"
                                                        value={form.data.address_line_2}
                                                        onChange={(e) => form.setData('address_line_2', e.target.value)}
                                                    />
                                                </Field>
                                                <Field label="State" error={form.errors.state}>
                                                    <AsyncLocationSelect
                                                        type="state"
                                                        placeholder="Select state"
                                                        value={form.data.state}
                                                        onValueChange={(value) =>
                                                            form.setData({
                                                                ...form.data,
                                                                state: value,
                                                                district: '',
                                                                city: '',
                                                                postal_code: '',
                                                                latitude: '',
                                                                longitude: '',
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="District" error={form.errors.district}>
                                                    <AsyncLocationSelect
                                                        type="district"
                                                        state={form.data.state}
                                                        placeholder="Select district"
                                                        value={form.data.district}
                                                        onValueChange={(value) =>
                                                            form.setData({
                                                                ...form.data,
                                                                district: value,
                                                                city: '',
                                                                postal_code: '',
                                                                latitude: '',
                                                                longitude: '',
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="City" error={form.errors.city}>
                                                    <AsyncLocationSelect
                                                        type="city"
                                                        state={form.data.state}
                                                        placeholder="Select city"
                                                        value={form.data.city}
                                                        onValueChange={(value) =>
                                                            form.setData({ ...form.data, city: value, postal_code: '', latitude: '', longitude: '' })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Pincode" error={form.errors.postal_code}>
                                                    <AsyncLocationSelect
                                                        type="pincode"
                                                        city={form.data.city}
                                                        placeholder="Select pincode"
                                                        value={form.data.postal_code}
                                                        onValueChange={(value) => form.setData('postal_code', value)}
                                                    />
                                                </Field>
                                                <Field label="Country" error={form.errors.country}>
                                                    <Input
                                                        autoComplete="new-password"
                                                        data-form-type="other"
                                                        placeholder="Country"
                                                        value={form.data.country}
                                                        onChange={(e) => form.setData('country', e.target.value)}
                                                    />
                                                </Field>
                                                <div className="flex flex-wrap items-end gap-2 sm:col-span-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() => void geocodeAddress('local')}
                                                        disabled={geocodingAddress !== null}
                                                    >
                                                        <MapPin className="size-4" />{' '}
                                                        {geocodingAddress === 'local' ? 'Locating…' : 'Fetch map location'}
                                                    </Button>
                                                    {mapError && activeAddress === 'local' && (
                                                        <p className="w-full text-sm font-medium text-rose-600">{mapError}</p>
                                                    )}
                                                    {form.data.latitude && form.data.longitude && (
                                                        <a
                                                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:border-emerald-700 hover:bg-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500/30 focus-visible:outline-none"
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            href={`https://wa.me/?text=${encodeURIComponent(`Current address: https://www.google.com/maps?q=${form.data.latitude},${form.data.longitude}`)}`}
                                                        >
                                                            <MessageCircle className="size-4" />
                                                            <span>Share on WhatsApp</span>
                                                            <span className="hidden text-xs font-medium text-emerald-100 sm:inline">
                                                                ({Number(form.data.latitude).toFixed(5)}, {Number(form.data.longitude).toFixed(5)})
                                                            </span>
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        </fieldset>
                                        {!form.data.permanent_same_as_local && (
                                            <fieldset className={`form-fieldset !m-0 ${activeAddress === 'permanent' ? '' : 'hidden'}`}>
                                                <legend>Permanent address</legend>
                                                <div className="grid gap-4 pt-3 sm:grid-cols-2">
                                                    <Field label="Address line 1" error={form.errors.permanent_address_line_1}>
                                                        <Input
                                                            autoComplete="new-password"
                                                            data-form-type="other"
                                                            placeholder="Address line 1"
                                                            value={form.data.permanent_address_line_1}
                                                            onChange={(e) => form.setData('permanent_address_line_1', e.target.value)}
                                                        />
                                                    </Field>
                                                    <Field label="Address line 2" error={form.errors.permanent_address_line_2}>
                                                        <Input
                                                            autoComplete="new-password"
                                                            data-form-type="other"
                                                            placeholder="Address line 2"
                                                            value={form.data.permanent_address_line_2}
                                                            onChange={(e) => form.setData('permanent_address_line_2', e.target.value)}
                                                        />
                                                    </Field>
                                                    <Field label="State" error={form.errors.permanent_state}>
                                                        <AsyncLocationSelect
                                                            type="state"
                                                            placeholder="Select state"
                                                            value={form.data.permanent_state}
                                                            onValueChange={(value) =>
                                                                form.setData({
                                                                    ...form.data,
                                                                    permanent_state: value,
                                                                    permanent_district: '',
                                                                    permanent_city: '',
                                                                    permanent_postal_code: '',
                                                                    permanent_latitude: '',
                                                                    permanent_longitude: '',
                                                                })
                                                            }
                                                        />
                                                    </Field>
                                                    <Field label="District" error={form.errors.permanent_district}>
                                                        <AsyncLocationSelect
                                                            type="district"
                                                            state={form.data.permanent_state}
                                                            placeholder="Select district"
                                                            value={form.data.permanent_district}
                                                            onValueChange={(value) =>
                                                                form.setData({
                                                                    ...form.data,
                                                                    permanent_district: value,
                                                                    permanent_city: '',
                                                                    permanent_postal_code: '',
                                                                    permanent_latitude: '',
                                                                    permanent_longitude: '',
                                                                })
                                                            }
                                                        />
                                                    </Field>
                                                    <Field label="City" error={form.errors.permanent_city}>
                                                        <AsyncLocationSelect
                                                            type="city"
                                                            state={form.data.permanent_state}
                                                            placeholder="Select city"
                                                            value={form.data.permanent_city}
                                                            onValueChange={(value) =>
                                                                form.setData({
                                                                    ...form.data,
                                                                    permanent_city: value,
                                                                    permanent_postal_code: '',
                                                                    permanent_latitude: '',
                                                                    permanent_longitude: '',
                                                                })
                                                            }
                                                        />
                                                    </Field>
                                                    <Field label="Pincode" error={form.errors.permanent_postal_code}>
                                                        <AsyncLocationSelect
                                                            type="pincode"
                                                            city={form.data.permanent_city}
                                                            placeholder="Select pincode"
                                                            value={form.data.permanent_postal_code}
                                                            onValueChange={(value) => form.setData('permanent_postal_code', value)}
                                                        />
                                                    </Field>
                                                    <Field label="Country" error={form.errors.permanent_country}>
                                                        <Input
                                                            placeholder="Country"
                                                            value={form.data.permanent_country}
                                                            onChange={(e) => form.setData('permanent_country', e.target.value)}
                                                        />
                                                    </Field>
                                                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => void geocodeAddress('permanent')}
                                                            disabled={geocodingAddress !== null}
                                                        >
                                                            <MapPin className="size-4" />{' '}
                                                            {geocodingAddress === 'permanent' ? 'Locating…' : 'Fetch map location'}
                                                        </Button>
                                                        {mapError && activeAddress === 'permanent' && (
                                                            <p className="w-full text-sm font-medium text-rose-600">{mapError}</p>
                                                        )}
                                                        {form.data.permanent_latitude && form.data.permanent_longitude && (
                                                            <a
                                                                className="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:border-emerald-700 hover:bg-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500/30 focus-visible:outline-none"
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                href={`https://wa.me/?text=${encodeURIComponent(`Permanent address: https://www.google.com/maps?q=${form.data.permanent_latitude},${form.data.permanent_longitude}`)}`}
                                                            >
                                                                <MessageCircle className="size-4" />
                                                                <span>Share on WhatsApp</span>
                                                                <span className="hidden text-xs font-medium text-emerald-100 sm:inline">
                                                                    ({Number(form.data.permanent_latitude).toFixed(5)},{' '}
                                                                    {Number(form.data.permanent_longitude).toFixed(5)})
                                                                </span>
                                                            </a>
                                                        )}
                                                    </div>
                                                </div>
                                            </fieldset>
                                        )}
                                        {activeAddress === 'local' && (
                                            <div className="min-h-72 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-inner">
                                                {addressMapUrl(form.data.latitude, form.data.longitude) ? (
                                                    <iframe
                                                        title="Current address map"
                                                        className="h-full min-h-72 w-full"
                                                        loading="lazy"
                                                        src={addressMapUrl(form.data.latitude, form.data.longitude)}
                                                    />
                                                ) : (
                                                    <div className="grid min-h-72 place-items-center p-8 text-center">
                                                        <div>
                                                            <span className="bg-brand-aqua/20 text-brand-teal mx-auto grid size-12 place-items-center rounded-full">
                                                                <MapPin className="size-5" />
                                                            </span>
                                                            <p className="mt-3 text-sm font-semibold text-slate-700">Current address map</p>
                                                            <p className="mt-1 text-xs text-slate-500">
                                                                Complete the address and select Fetch map location.
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        {!form.data.permanent_same_as_local && activeAddress === 'permanent' && (
                                            <div className="min-h-72 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-inner">
                                                {addressMapUrl(form.data.permanent_latitude, form.data.permanent_longitude) ? (
                                                    <iframe
                                                        title="Permanent address map"
                                                        className="h-full min-h-72 w-full"
                                                        loading="lazy"
                                                        src={addressMapUrl(form.data.permanent_latitude, form.data.permanent_longitude)}
                                                    />
                                                ) : (
                                                    <div className="grid min-h-72 place-items-center p-8 text-center">
                                                        <div>
                                                            <span className="bg-brand-aqua/20 text-brand-teal mx-auto grid size-12 place-items-center rounded-full">
                                                                <MapPin className="size-5" />
                                                            </span>
                                                            <p className="mt-3 text-sm font-semibold text-slate-700">Permanent address map</p>
                                                            <p className="mt-1 text-xs text-slate-500">
                                                                Complete the address and select Fetch map location.
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}

                            {formStep === 3 && (
                                <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <div className="mb-4">
                                        <h3 className="font-semibold text-slate-900">
                                            Education <span className="font-normal text-slate-500">(optional)</span>
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">Leave empty to skip.</p>
                                    </div>
                                    <div className="space-y-4">
                                        {form.data.educations.map((education, educationIndex) => {
                                            const educationErrors = form.errors as Record<string, string>;
                                            const updateEducation = (changes: Partial<EducationForm>) =>
                                                form.setData(
                                                    'educations',
                                                    form.data.educations.map((item, index) =>
                                                        index === educationIndex ? { ...item, ...changes } : item,
                                                    ),
                                                );
                                            return (
                                                <div
                                                    key={education.id ?? educationIndex}
                                                    className="overflow-hidden rounded-xl border border-slate-200"
                                                >
                                                    <div className="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                                                        <span className="text-brand-teal text-xs font-bold tracking-wide uppercase">
                                                            Education {educationIndex + 1}
                                                        </span>
                                                        {form.data.educations.length > 1 && (
                                                            <button
                                                                type="button"
                                                                className="text-xs font-semibold text-rose-600"
                                                                onClick={() =>
                                                                    form.setData(
                                                                        'educations',
                                                                        form.data.educations.filter((_, index) => index !== educationIndex),
                                                                    )
                                                                }
                                                            >
                                                                Remove
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div className="grid gap-4 p-4 sm:grid-cols-2">
                                                        <Field
                                                            label="Education level"
                                                            error={educationErrors[`educations.${educationIndex}.education_level`]}
                                                        >
                                                            <Select
                                                                value={education.education_level || undefined}
                                                                onValueChange={(value) => updateEducation({ education_level: value })}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select education level" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {[
                                                                        ...educationLevels,
                                                                        ...(education.education_level &&
                                                                        !educationLevels.includes(
                                                                            education.education_level as (typeof educationLevels)[number],
                                                                        )
                                                                            ? [education.education_level]
                                                                            : []),
                                                                    ].map((level) => (
                                                                        <SelectItem key={level} value={level}>
                                                                            {level}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </Field>
                                                        <Field
                                                            label="Institution name"
                                                            error={educationErrors[`educations.${educationIndex}.institution_name`]}
                                                        >
                                                            <Input
                                                                placeholder="Institution name"
                                                                value={education.institution_name}
                                                                onChange={(e) => updateEducation({ institution_name: e.target.value })}
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="University name"
                                                            error={educationErrors[`educations.${educationIndex}.university_name`]}
                                                        >
                                                            <Input
                                                                placeholder="University name"
                                                                value={education.university_name}
                                                                onChange={(e) => updateEducation({ university_name: e.target.value })}
                                                            />
                                                        </Field>
                                                        <Field label="Grade" error={educationErrors[`educations.${educationIndex}.grade`]}>
                                                            <Input
                                                                placeholder="Grade"
                                                                value={education.grade}
                                                                onChange={(e) => updateEducation({ grade: e.target.value })}
                                                            />
                                                        </Field>
                                                        <label className="flex h-[38px] items-center gap-2 text-sm font-medium">
                                                            <Checkbox
                                                                checked={education.currently_studying}
                                                                onCheckedChange={(checked) =>
                                                                    updateEducation({
                                                                        currently_studying: checked === true,
                                                                        end_date: checked === true ? '' : education.end_date,
                                                                    })
                                                                }
                                                            />{' '}
                                                            Currently studying
                                                        </label>
                                                        <div className="sm:col-span-2">
                                                            <Label className="mb-1.5 block text-sm font-semibold">Education document / image</Label>
                                                            <Input
                                                                type="file"
                                                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                                onChange={(e) => updateEducation({ document: e.target.files?.[0] ?? null })}
                                                            />
                                                            <p className="mt-1 text-xs text-slate-500">PDF, JPG, PNG or WebP up to 10 MB.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData('educations', [
                                                    ...form.data.educations,
                                                    {
                                                        education_level: '',
                                                        degree_name: '',
                                                        institution_name: '',
                                                        university_name: '',
                                                        start_date: '',
                                                        end_date: '',
                                                        grade: '',
                                                        currently_studying: false,
                                                        document: null,
                                                    },
                                                ])
                                            }
                                        >
                                            <Plus className="size-4" /> Add more education
                                        </Button>
                                    </div>
                                </section>
                            )}

                            {formStep === 4 && (
                                <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <div className="mb-4">
                                        <h3 className="font-semibold text-slate-900">
                                            Banking details <span className="font-normal text-slate-500">(optional)</span>
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">Enter the employee's primary bank account details.</p>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Bank name" error={form.errors.bank_name}>
                                            <Input
                                                placeholder="Bank name"
                                                value={form.data.bank_name}
                                                onChange={(event) => form.setData('bank_name', event.target.value)}
                                            />
                                        </Field>
                                        <Field label="Account number" error={form.errors.account_number}>
                                            <Input
                                                placeholder="Account number"
                                                value={form.data.account_number}
                                                onChange={(event) => form.setData('account_number', event.target.value)}
                                            />
                                        </Field>
                                        <Field label="Account type" error={form.errors.account_type}>
                                            <Select
                                                value={form.data.account_type || undefined}
                                                onValueChange={(value) => form.setData('account_type', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select account type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="savings">Savings</SelectItem>
                                                    <SelectItem value="current">Current</SelectItem>
                                                    <SelectItem value="salary">Salary</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field label="Branch name" error={form.errors.branch_name}>
                                            <Input
                                                placeholder="Branch name"
                                                value={form.data.branch_name}
                                                onChange={(event) => form.setData('branch_name', event.target.value)}
                                            />
                                        </Field>
                                        <Field label="IFSC code" error={form.errors.ifsc_code}>
                                            <Input
                                                placeholder="IFSC code"
                                                value={form.data.ifsc_code}
                                                onChange={(event) => form.setData('ifsc_code', event.target.value.toUpperCase())}
                                            />
                                        </Field>
                                    </div>
                                </section>
                            )}

                            {formStep === 6 && (
                                <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                    <div className="mb-5 border-b border-slate-200 pb-4">
                                        <h3 className="text-lg font-semibold text-slate-900">Review employee information</h3>
                                        <p className="mt-1 text-sm text-slate-500">
                                            Confirm all details below before {editingUser ? 'updating' : 'creating'} the employee.
                                        </p>
                                    </div>
                                    <div className="grid gap-5 lg:grid-cols-2">
                                        <ReviewSection
                                            title="Personal information"
                                            items={[
                                                ['Name', [form.data.first_name, form.data.last_name].filter(Boolean).join(' ') || '—'],
                                                ['Gender', form.data.gender || '—'],
                                                ['Marital status', form.data.marital_status || '—'],
                                                ['Date of birth', form.data.date_of_birth || '—'],
                                                ['Anniversary date', form.data.anniversary_date || '—'],
                                                ['Aadhaar', form.data.aadhaar_number ? `•••• •••• ${form.data.aadhaar_number.slice(-4)}` : '—'],
                                                ['PAN', form.data.pan_number || '—'],
                                            ]}
                                        />
                                        <ReviewSection
                                            title="Employment information"
                                            items={[
                                                ['Company', form.data.company || '—'],
                                                ['Sitting location', form.data.sitting_location || '—'],
                                                [
                                                    'Department',
                                                    departments.find((item) => String(item.id) === form.data.department_id)?.department_name ?? '—',
                                                ],
                                                [
                                                    'Designation',
                                                    designations.find((item) => String(item.id) === form.data.designation_id)?.designation_name ??
                                                        '—',
                                                ],
                                                ['Role', form.data.role || '—'],
                                            ]}
                                        />
                                        <ReviewSection
                                            title="Login information"
                                            items={[
                                                ['Username or email', form.data.username || '—'],
                                                ['Password', editingUser ? (form.data.password ? 'Will be changed' : 'Unchanged') : 'Set securely'],
                                            ]}
                                        />
                                        <ReviewSection
                                            title="Banking information"
                                            items={[
                                                ['Bank', form.data.bank_name || '—'],
                                                [
                                                    'Account number',
                                                    form.data.account_number ? `••••${form.data.account_number.slice(-4)}` : '—',
                                                ],
                                                ['Account type', form.data.account_type || '—'],
                                                ['Branch', form.data.branch_name || '—'],
                                                ['IFSC', form.data.ifsc_code || '—'],
                                            ]}
                                        />
                                        <ReviewSection
                                            title="Address information"
                                            items={[
                                                ['Address', [form.data.address_line_1, form.data.address_line_2].filter(Boolean).join(', ') || '—'],
                                                [
                                                    'Location',
                                                    [form.data.state, form.data.district, form.data.city, form.data.postal_code]
                                                        .filter(Boolean)
                                                        .join(', ') || '—',
                                                ],
                                                ['Country', form.data.country || '—'],
                                            ]}
                                        />
                                        <div className="rounded-xl border border-slate-200 p-4 lg:col-span-2">
                                            <h4 className="mb-3 font-semibold text-slate-900">Contact information</h4>
                                            <div className="overflow-hidden rounded-lg border border-slate-200">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="bg-slate-50 text-xs text-slate-600 uppercase">
                                                        <tr>
                                                            <th className="px-3 py-2">Type</th>
                                                            <th className="px-3 py-2">Contact</th>
                                                            <th className="px-3 py-2">Primary</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-200">
                                                        {form.data.contacts
                                                            .filter((contact) => contact.contact_value)
                                                            .map((contact, index) => (
                                                                <tr key={index}>
                                                                    <td className="px-3 py-2 capitalize">{contact.contact_type}</td>
                                                                    <td className="px-3 py-2">{contact.contact_value}</td>
                                                                    <td className="px-3 py-2">{contact.is_primary ? 'Yes' : 'No'}</td>
                                                                </tr>
                                                            ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div className="rounded-xl border border-slate-200 p-4 lg:col-span-2">
                                            <h4 className="mb-3 font-semibold text-slate-900">Education</h4>
                                            {form.data.educations.some(
                                                (education) => education.education_level || education.degree_name || education.institution_name,
                                            ) ? (
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    {form.data.educations
                                                        .filter(
                                                            (education) =>
                                                                education.education_level || education.degree_name || education.institution_name,
                                                        )
                                                        .map((education, index) => (
                                                            <div key={index} className="rounded-lg bg-slate-50 p-3 text-sm">
                                                                <b>
                                                                    {education.degree_name || education.education_level || `Education ${index + 1}`}
                                                                </b>
                                                                <p className="mt-1 text-slate-600">
                                                                    {[education.institution_name, education.university_name]
                                                                        .filter(Boolean)
                                                                        .join(' · ')}
                                                                </p>
                                                                <p className="mt-1 text-xs text-slate-500">
                                                                    {[
                                                                        education.start_date,
                                                                        education.currently_studying ? 'Present' : education.end_date,
                                                                    ]
                                                                        .filter(Boolean)
                                                                        .join(' – ')}
                                                                </p>
                                                            </div>
                                                        ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-slate-500">No education information added.</p>
                                            )}
                                        </div>
                                    </div>
                                </section>
                            )}
                        </div>
                        <DialogFooter className="sticky bottom-0 z-20 shrink-0 gap-3 border-t border-slate-200 bg-white px-4 py-2.5 shadow-[0_-8px_20px_-16px_rgba(15,23,42,0.45)] sm:px-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                className="h-10 w-full border-slate-300 bg-white px-5 font-semibold sm:w-auto"
                            >
                                Cancel
                            </Button>
                            <div className="flex w-full gap-2 sm:ml-auto sm:w-auto">
                                {formStep > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 flex-1 px-5 font-semibold sm:flex-none"
                                        onClick={() => setFormStep((step) => step - 1)}
                                    >
                                        <ChevronLeft className="size-4" /> Back
                                    </Button>
                                )}
                                {formStep < formSteps.length - 1 ? (
                                    <Button
                                        type="button"
                                        onClick={nextFormStep}
                                        className="bg-brand-teal hover:bg-brand-plum h-10 flex-1 px-6 font-semibold text-white shadow-sm sm:flex-none"
                                    >
                                        Next <ChevronRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        onClick={() => {
                                            explicitSubmitRef.current = true;
                                        }}
                                        disabled={form.processing}
                                        className="bg-brand-teal hover:bg-brand-plum h-10 min-w-32 flex-1 font-semibold text-white shadow-sm sm:flex-none"
                                    >
                                        {form.processing ? 'Saving…' : editingUser ? 'Save changes' : 'Save user'}
                                    </Button>
                                )}
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="max-w-lg p-0 sm:p-0">
                    <MasterDialogHeader title="Import users from Excel" />
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            importForm.post('/admin/user-master/import', {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    setImportOpen(false);
                                    importForm.reset();
                                },
                            });
                        }}
                    >
                        <div className="space-y-4 p-6">
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                <b className="block">Duplicate-safe import</b>
                                <span>Rows matching an existing email/login or employee ID are skipped automatically.</span>
                            </div>
                            <div className="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                <Upload className="mx-auto mb-2 size-8 text-slate-400" />
                                <Label htmlFor="user-excel" className="text-brand-teal cursor-pointer font-semibold">
                                    Choose Excel or CSV file
                                </Label>
                                <Input
                                    id="user-excel"
                                    type="file"
                                    accept=".xlsx,.csv"
                                    className="mt-3 bg-white"
                                    onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)}
                                />
                                <p className="mt-2 text-xs text-slate-500">
                                    Required columns: first_name, last_name, email. Optional: employee_id, password, mobile, gender, company,
                                    sitting_location, role.
                                </p>
                            </div>
                            <InputError message={importForm.errors.file} />
                        </div>
                        <DialogFooter className="border-t border-slate-200 px-6 py-4">
                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>
                                Cancel
                            </Button>
                            <Button
                                disabled={!importForm.data.file || importForm.processing}
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <Upload className="size-4" />
                                {importForm.processing ? 'Importing…' : 'Upload and import'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function DatePickerInput({
    value,
    onChange,
    placeholder,
    max,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    max?: string;
}) {
    const databaseValue = dateForDatabase(value);
    const pickerValue = /^\d{4}-\d{2}-\d{2}$/.test(databaseValue) ? databaseValue : '';

    return (
        <div className="relative h-[38px]">
            <div className="flex h-[38px] items-center rounded-md border border-slate-300 bg-white px-3 pr-10 text-sm shadow-sm">
                <span className={value ? 'text-slate-900' : 'text-slate-500'}>{value || placeholder}</span>
                <CalendarDays className="text-brand-teal absolute right-3 size-4" />
            </div>
            <input
                type="date"
                value={pickerValue}
                max={max}
                aria-label={placeholder}
                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                onChange={(event) => onChange(dateForInput(event.target.value))}
            />
        </div>
    );
}

function AsyncLocationSelect({
    type,
    value,
    onValueChange,
    placeholder,
    state = '',
    city = '',
}: {
    type: 'state' | 'district' | 'city' | 'pincode';
    value: string;
    onValueChange: (value: string) => void;
    placeholder: string;
    state?: string;
    city?: string;
}) {
    const [query, setQuery] = useState(value);
    const [options, setOptions] = useState<Array<{ value: string; label: string }>>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const disabled = (type === 'district' && !state) || (type === 'pincode' && !city);

    useEffect(() => setQuery(value), [value]);
    useEffect(() => {
        if (!open || disabled) return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const params = new URLSearchParams({ type, query: query === value ? '' : query });
                if (state) params.set('state', state);
                if (city) params.set('city', city);
                const response = await fetch(`/admin/user-master/location-options?${params.toString()}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Unable to load location options.');
                setOptions((await response.json()) as Array<{ value: string; label: string }>);
            } catch (error) {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setOptions([]);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query, value, type, state, city, disabled]);

    return (
        <div className="relative">
            <div className="group relative">
                <Search className="group-focus-within:text-brand-teal pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400 transition-colors" />
                <input
                    role="combobox"
                    autoComplete="new-password"
                    data-form-type="other"
                    aria-label={placeholder}
                    aria-expanded={open}
                    value={query}
                    disabled={disabled}
                    placeholder={disabled ? (type === 'district' ? 'Choose a state first' : 'Choose a city first') : placeholder}
                    className="focus:border-brand-teal focus:ring-brand-teal/15 !h-10 w-full rounded-lg border border-slate-300 bg-white !pr-10 !pl-10 text-sm text-slate-800 shadow-sm transition-all outline-none placeholder:text-slate-400 focus:ring-3 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-400 disabled:shadow-none"
                    onFocus={(event) => {
                        setOpen(true);
                        event.currentTarget.select();
                    }}
                    onBlur={() =>
                        window.setTimeout(() => {
                            setOpen(false);
                            setQuery(value);
                        }, 150)
                    }
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && options[0]) {
                            event.preventDefault();
                            onValueChange(options[0].value);
                            setQuery(options[0].label);
                            setOpen(false);
                        }
                        if (event.key === 'Escape') setOpen(false);
                    }}
                />
                <ChevronDown
                    className={`pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 transition-colors ${disabled ? 'text-slate-300' : 'group-focus-within:text-brand-teal text-slate-500'}`}
                />
            </div>
            {open && !disabled && (
                <div className="absolute z-50 mt-1.5 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10">
                    {loading ? (
                        <p className="px-3 py-2 text-sm text-slate-500">Loading…</p>
                    ) : options.length > 0 ? (
                        options.map((option) => (
                            <button
                                type="button"
                                key={option.value}
                                className="hover:bg-brand-aqua/15 flex min-h-9 w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm text-slate-700 transition-colors"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    onValueChange(option.value);
                                    setQuery(option.label);
                                    setOpen(false);
                                }}
                            >
                                {option.label}
                                {option.value === value && <Check className="text-brand-teal size-4" />}
                            </button>
                        ))
                    ) : (
                        <p className="px-3 py-2 text-sm text-slate-500">No database records found</p>
                    )}
                </div>
            )}
        </div>
    );
}

function SearchableSelect({
    value,
    onValueChange,
    options,
    placeholder,
    fieldLabel,
}: {
    value: string;
    onValueChange: (value: string) => void;
    options: Array<{ value: string; label: string }>;
    placeholder: string;
    fieldLabel?: string;
}) {
    const selectedLabel = options.find((option) => option.value === value)?.label ?? '';
    const [query, setQuery] = useState(selectedLabel);
    const [open, setOpen] = useState(false);

    useEffect(() => setQuery(selectedLabel), [selectedLabel]);
    const filteredOptions = options.filter((option) => option.label.toLowerCase().includes(query.toLowerCase().trim()));

    return (
        <div className="relative">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                {fieldLabel && (
                    <span className="pointer-events-none absolute top-0.5 left-9 z-10 text-[8px] font-bold tracking-wide text-slate-500 uppercase">
                        {fieldLabel}
                    </span>
                )}
                <input
                    role="combobox"
                    autoComplete="new-password"
                    data-form-type="other"
                    aria-label={fieldLabel ?? placeholder}
                    aria-expanded={open}
                    aria-autocomplete="list"
                    value={query}
                    placeholder={placeholder}
                    className={`focus:border-brand-teal focus:ring-brand-teal/20 h-[38px] w-full rounded-md border border-slate-300 bg-white !pr-9 !pl-9 text-sm shadow-sm transition outline-none focus:ring-2 ${fieldLabel ? '!pt-3' : ''}`}
                    onFocus={(event) => {
                        setOpen(true);
                        event.currentTarget.select();
                    }}
                    onBlur={() =>
                        window.setTimeout(() => {
                            setOpen(false);
                            setQuery(options.find((option) => option.value === value)?.label ?? '');
                        }, 150)
                    }
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && filteredOptions.length > 0) {
                            event.preventDefault();
                            onValueChange(filteredOptions[0].value);
                            setQuery(filteredOptions[0].label);
                            setOpen(false);
                        }
                        if (event.key === 'Escape') setOpen(false);
                    }}
                />
                <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
            </div>
            {open && (
                <div className="absolute z-50 mt-1 max-h-52 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-lg">
                    {filteredOptions.length > 0 ? (
                        filteredOptions.map((option) => (
                            <button
                                type="button"
                                key={option.value}
                                className="flex h-auto min-h-9 w-full items-center justify-between rounded px-3 py-2 text-left text-sm hover:bg-slate-100"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    onValueChange(option.value);
                                    setQuery(option.label);
                                    setOpen(false);
                                }}
                            >
                                {option.label}
                                {option.value === value && <Check className="text-brand-teal size-4" />}
                            </button>
                        ))
                    ) : (
                        <p className="px-3 py-2 text-sm text-slate-500">No options found</p>
                    )}
                </div>
            )}
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5 [&_button]:h-[38px] [&_input]:h-[38px] [&_input]:border-slate-300 [&_input]:bg-white [&_input]:px-3 [&_input]:shadow-sm [&_input]:transition-colors [&_input]:focus:bg-white">
            <Label className="sr-only">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function ReviewSection({ title, items }: { title: string; items: Array<[string, string]> }) {
    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <h4 className="mb-3 font-semibold text-slate-900">{title}</h4>
            <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                {items.map(([label, value]) => (
                    <div key={label} className="border-b border-slate-100 pb-2">
                        <dt className="text-xs font-medium text-slate-500">{label}</dt>
                        <dd className="mt-0.5 font-medium text-slate-800 capitalize">{value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
