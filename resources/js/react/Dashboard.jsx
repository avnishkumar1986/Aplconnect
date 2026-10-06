import React from "react";
import { DashboardHeading, KpiCard } from "./components/ui";
import {
    WelcomeCard,
    MonthlyCard,
    DirectoryCard,
} from "./components/ProfileCards";
import { EmployeeChart, Timeline, Locations } from "./components/Analytics";
import ActivityTable from "./components/ActivityTable";
export default function Dashboard({ stats, user }) {
    return (
        <>
            <DashboardHeading />
            <div className="grid gap-5 xl:grid-cols-[minmax(310px,.9fr)_minmax(0,2fr)]">
                <div className="space-y-5">
                    <WelcomeCard user={user} stats={stats} />
                    <MonthlyCard stats={stats} />
                </div>
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <KpiCard
                            label="Users"
                            value={stats.Users ?? 0}
                            icon="U"
                        />
                        <KpiCard
                            label="Accounts"
                            value={stats.Accounts ?? 0}
                            icon="A"
                        />
                        <KpiCard
                            label="User Types"
                            value={stats["User types"] ?? 0}
                            icon="T"
                        />
                    </div>
                    <EmployeeChart />
                </div>
            </div>
            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <DirectoryCard />
                <Timeline />
                <Locations />
            </div>
            <div className="mt-5">
                <ActivityTable />
            </div>
        </>
    );
}
